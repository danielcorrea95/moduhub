<?php

namespace Tests\Feature\Invoices;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Invoices\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\InteractsWithInvoices;
use Tests\TestCase;

class InvoicePanelTest extends TestCase
{
    use InteractsWithInvoices, RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->owner] = $this->makeInvoicesTenant();
    }

    public function test_listing_warns_when_the_issuer_is_not_ready(): void
    {
        $this->actingAs($this->owner)
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Invoices/Index')
                ->where('issuerReady', false)
                ->has('invoices.data', 0));
    }

    public function test_create_page_shows_the_issuer_summary(): void
    {
        $this->configureIssuer($this->tenant);

        $this->actingAs($this->owner)
            ->get(route('invoices.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Invoices/Create')
                ->where('issuer.ready_to_issue', true)
                ->where('issuer.next_dps_number', 1)
                ->has('options.iss_taxation'));
    }

    public function test_member_issues_from_the_panel_and_lands_on_the_invoice(): void
    {
        $this->configureIssuer($this->tenant);
        $member = User::factory()->create();
        $this->tenant->users()->attach($member, ['role' => 'member']);

        $response = $this->actingAs($member)
            ->post(route('invoices.store'), $this->invoicePayload([
                'external_id' => '',
                'taker' => ['address' => ['postal_code' => '', 'street' => '', 'number' => '', 'complement' => '', 'district' => '', 'city_code' => '']],
                'values' => ['iss_rate' => '', 'approximate_taxes' => ['federal' => '', 'state' => '', 'municipal' => '']],
            ]));

        $invoice = Invoice::withoutGlobalScopes()->firstOrFail();
        $response->assertRedirect(route('invoices.show', $invoice))->assertSessionHas('success', 'NFS-e emitida.');

        $this->assertSame(Invoice::STATUS_ISSUED, $invoice->status);
        $this->assertSame($member->getKey(), $invoice->creator_id);
        $this->assertNull($invoice->taker['address'], 'Endereço vazio do formulário não vai para a DPS.');
        $this->assertStringNotContainsString('<end>', $invoice->dps_xml);

        $this->actingAs($member)
            ->get(route('invoices.show', $invoice))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Invoices/Show')
                ->where('invoice.status', Invoice::STATUS_ISSUED)
                ->where('canCancel', false)
                ->missing('invoice.dps_xml'));

        // Membro emite, mas cancelar é de quem administra o workspace.
        $this->actingAs($member)
            ->post(route('invoices.cancel', $invoice), ['reason_code' => 1, 'reason' => 'Valor informado errado'])
            ->assertForbidden();
    }

    public function test_rejection_is_shown_on_the_invoice(): void
    {
        $this->configureIssuer($this->tenant);
        $this->sefin()->rejectNext([['code' => 'E0116', 'message' => 'Inscrição municipal do prestador não cadastrada.']]);

        $this->actingAs($this->owner)->post(route('invoices.store'), $this->invoicePayload())
            ->assertSessionHas('error');

        $invoice = Invoice::withoutGlobalScopes()->firstOrFail();
        $this->actingAs($this->owner)
            ->get(route('invoices.show', $invoice))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoice.status', Invoice::STATUS_REJECTED)
                ->where('invoice.rejection.0.code', 'E0116'));
    }

    public function test_rejected_invoice_is_corrected_and_resent_as_a_new_dps(): void
    {
        $this->configureIssuer($this->tenant);
        $this->sefin()->rejectNext([['code' => 'E0312', 'message' => 'Código de tributação nacional não administrado pelo município.']]);
        $this->actingAs($this->owner)->post(route('invoices.store'), $this->invoicePayload());
        $rejected = Invoice::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($this->owner)
            ->get(route('invoices.create', ['from' => $rejected->getKey()]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('retryOf.id', $rejected->getKey())
                ->where('retryOf.rejection.0.code', 'E0312')
                ->where('retryOf.draft.external_id', 'pedido-1001')
                ->where('retryOf.draft.taker.document', '11222333000181')
                ->where('retryOf.draft.service.national_tax_code', '010701')
                ->where('retryOf.draft.values.amount', '1500.00'));

        $this->actingAs($this->owner)
            ->post(route('invoices.store'), $this->invoicePayload(['service' => ['national_tax_code' => '01.05.01']]))
            ->assertSessionHas('success', 'NFS-e emitida.');

        $resent = Invoice::withoutGlobalScopes()->where('status', Invoice::STATUS_ISSUED)->firstOrFail();
        $this->assertSame(2, $resent->dps_number);
        $this->assertSame('010501', $resent->service['national_tax_code']);
        $this->assertTrue($rejected->fresh()->isRejected(), 'A rejeitada continua no histórico.');
    }

    public function test_only_rejected_invoices_prefill_the_form(): void
    {
        $this->configureIssuer($this->tenant);
        $this->actingAs($this->owner)->post(route('invoices.store'), $this->invoicePayload());
        $issued = Invoice::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($this->owner)
            ->get(route('invoices.create', ['from' => $issued->getKey()]))
            ->assertInertia(fn (Assert $page) => $page->where('retryOf', null));
    }

    public function test_owner_syncs_and_cancels(): void
    {
        $this->configureIssuer($this->tenant);
        $this->sefin()->failNext();
        $this->actingAs($this->owner)->post(route('invoices.store'), $this->invoicePayload());
        $invoice = Invoice::withoutGlobalScopes()->firstOrFail();
        $this->assertTrue($invoice->isPending());

        $this->actingAs($this->owner)
            ->post(route('invoices.sync', $invoice))
            ->assertSessionHas('success', 'NFS-e emitida.');

        $this->actingAs($this->owner)
            ->post(route('invoices.cancel', $invoice), ['reason_code' => 9, 'reason' => 'Emitida em duplicidade'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertTrue($invoice->fresh()->isCancelled());

        $pdf = $this->actingAs($this->owner)->get(route('invoices.pdf', $invoice))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('CANCELADA', (new \Smalot\PdfParser\Parser)->parseContent($pdf->getContent())->getText());

        $this->actingAs($this->owner)->get(route('invoices.xml', $invoice))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function test_search_finds_by_taker_document(): void
    {
        $this->configureIssuer($this->tenant);
        $this->actingAs($this->owner)->post(route('invoices.store'), $this->invoicePayload());

        $this->actingAs($this->owner)
            ->get(route('invoices.index', ['search' => '11.222.333']))
            ->assertInertia(fn (Assert $page) => $page->has('invoices.data', 1)->where('statusCounts.issued', 1));

        $this->actingAs($this->owner)
            ->get(route('invoices.index', ['search' => 'ninguém']))
            ->assertInertia(fn (Assert $page) => $page->has('invoices.data', 0));
    }

    public function test_cannot_open_an_invoice_of_another_workspace(): void
    {
        $this->configureIssuer($this->tenant);
        $this->actingAs($this->owner)->post(route('invoices.store'), $this->invoicePayload());
        $invoice = Invoice::withoutGlobalScopes()->firstOrFail();

        [, $stranger] = $this->makeInvoicesTenant('Outra');
        // Outro login, outra sessão: o workspace atual do dono não pode vazar para o estranho.
        $this->flushSession();

        $this->actingAs($stranger)->get(route('invoices.show', $invoice))->assertNotFound();
    }
}
