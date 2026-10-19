<?php

namespace Tests\Feature\Invoices;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Invoices\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Smalot\PdfParser\Parser;
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
        $this->assertStringContainsString('CANCELADA', (new Parser)->parseContent($pdf->getContent())->getText());

        $this->actingAs($this->owner)->get(route('invoices.xml', $invoice))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function test_owner_replaces_an_issued_invoice(): void
    {
        $this->configureIssuer($this->tenant);
        $this->actingAs($this->owner)->post(route('invoices.store'), $this->invoicePayload());
        $original = Invoice::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($this->owner)
            ->get(route('invoices.create', ['replace' => $original->getKey()]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('replacing.id', $original->getKey())
                ->where('replacing.access_key', $original->access_key)
                ->where('replacing.draft.values.amount', '1500.00')
                ->where('options.substitution_reason.99', 'Outros'));

        $response = $this->actingAs($this->owner)
            ->post(route('invoices.replace', $original), $this->invoicePayload([
                'values' => ['amount' => '1450.00'],
                'substitution' => ['reason_code' => '99', 'reason' => 'Valor cobrado diferente do contratado'],
            ]))
            ->assertSessionHas('success', 'NFS-e substituta emitida. A original ficou como substituída.');

        $replacement = Invoice::withoutGlobalScopes()->whereKeyNot($original->getKey())->firstOrFail();
        $response->assertRedirect(route('invoices.show', $replacement));
        $this->assertTrue($replacement->isIssued());
        $this->assertSame($original->getKey(), $replacement->replaces_invoice_id);
        $this->assertStringContainsString('<chSubstda>'.$original->access_key.'</chSubstda><cMotivo>99</cMotivo>', $replacement->dps_xml);
        $this->assertTrue($original->fresh()->isReplaced());

        $this->actingAs($this->owner)
            ->get(route('invoices.show', $original))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoice.status', Invoice::STATUS_REPLACED)
                ->where('invoice.replacement.invoice_id', $replacement->getKey()));

        $pdf = $this->actingAs($this->owner)->get(route('invoices.pdf', $original))->assertOk();
        $this->assertStringContainsString('SUBSTITUÍDA', (new Parser)->parseContent($pdf->getContent())->getText());
    }

    public function test_rejected_replacement_keeps_replacing_the_same_invoice_when_corrected(): void
    {
        $this->configureIssuer($this->tenant);
        $this->actingAs($this->owner)->post(route('invoices.store'), $this->invoicePayload());
        $original = Invoice::withoutGlobalScopes()->firstOrFail();

        $this->sefin()->rejectNext([['code' => 'E0001', 'message' => 'Erro qualquer']]);
        $this->actingAs($this->owner)->post(route('invoices.replace', $original), $this->invoicePayload([
            'substitution' => ['reason_code' => '1'],
        ]));
        $rejected = Invoice::withoutGlobalScopes()->where('status', Invoice::STATUS_REJECTED)->firstOrFail();
        $this->assertSame('01', $rejected->substitution_reason_code);
        $this->assertTrue($original->fresh()->isIssued(), 'Substituta rejeitada não mexe na original.');

        $this->actingAs($this->owner)
            ->get(route('invoices.create', ['from' => $rejected->getKey()]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('retryOf.replaces.id', $original->getKey())
                ->where('retryOf.replaces.reason_code', '01'));
    }

    public function test_substitution_reason_needs_a_description_when_other(): void
    {
        $this->configureIssuer($this->tenant);
        $this->actingAs($this->owner)->post(route('invoices.store'), $this->invoicePayload());
        $original = Invoice::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($this->owner)
            ->post(route('invoices.replace', $original), $this->invoicePayload(['substitution' => ['reason_code' => '99']]))
            ->assertSessionHasErrors(['substitution.reason' => 'Descreva o motivo da substituição.']);
    }

    public function test_member_cannot_replace_and_only_issued_invoices_are_replaceable(): void
    {
        $this->configureIssuer($this->tenant);
        $member = User::factory()->create();
        $this->tenant->users()->attach($member, ['role' => 'member']);
        $this->actingAs($this->owner)->post(route('invoices.store'), $this->invoicePayload());
        $original = Invoice::withoutGlobalScopes()->firstOrFail();
        $payload = $this->invoicePayload(['substitution' => ['reason_code' => '05']]);

        $this->actingAs($member)->post(route('invoices.replace', $original), $payload)->assertForbidden();

        $this->actingAs($this->owner)->post(route('invoices.cancel', $original), ['reason_code' => 9, 'reason' => 'Emitida em duplicidade']);
        $this->actingAs($this->owner)
            ->post(route('invoices.replace', $original), $payload)
            ->assertSessionHasErrors(['substitution' => 'Só notas emitidas podem ser substituídas.']);
    }

    public function test_panel_payload_with_blank_optional_groups_is_accepted(): void
    {
        $this->configureIssuer($this->tenant);
        $blankAddress = ['country' => '', 'postal_code' => '', 'street' => '', 'number' => '', 'complement' => '', 'district' => '', 'city_code' => '', 'city' => '', 'state' => ''];

        $this->actingAs($this->owner)
            ->post(route('invoices.store'), $this->invoicePayload([
                'taker' => [
                    'nif' => '', 'nif_absence_reason' => '', 'municipal_registration' => '',
                    'address' => ['country' => '', 'city' => '', 'state' => ''],
                ],
                'intermediary' => null,
                'notify_taker' => true,
                'service' => [
                    'municipal_tax_code' => '', 'nbs_code' => '', 'result_country' => '', 'foreign_trade' => null,
                    'additional_info' => ['notes' => '', 'reference' => '', 'order' => '', 'order_items' => [], 'technical_document' => ''],
                    'construction' => null, 'event' => null,
                ],
                'values' => [
                    'iss_rate' => '', 'iss_withholder' => null, 'iss_taxation' => '1', 'immunity_type' => '', 'intermediary_received' => '',
                    'discounts' => ['unconditional' => '', 'conditional' => ''], 'deduction' => null,
                    'approximate_taxes' => ['federal' => '', 'state' => '', 'municipal' => ''],
                    'federal_taxes' => ['pis_cofins' => null, 'irrf' => null, 'csll' => null, 'inss' => null],
                    'municipal_benefit' => null, 'iss_suspension' => null,
                    'ibs_cbs' => [
                        'cst' => '', 'class_code' => '', 'operation_code' => '', 'final_consumer' => null,
                        'operation_type' => '', 'government_entity' => '', 'referenced_invoices' => [], 'recipient' => null, 'property' => null, 'reimbursements' => [],
                    ],
                ],
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'NFS-e emitida.');

        $this->actingAs($this->owner)
            ->post(route('invoices.store'), $this->invoicePayload([
                'external_id' => 'pedido-1002',
                'taker' => [
                    'document' => '', 'nif' => '', 'nif_absence_reason' => '1', 'name' => 'John Doe',
                    'address' => $blankAddress,
                ],
            ]))
            ->assertSessionHasNoErrors();

        $foreign = Invoice::withoutGlobalScopes()->where('external_id', 'pedido-1002')->firstOrFail();
        $this->assertNull($foreign->taker['address']);
        $this->assertStringContainsString('<toma><cNaoNIF>1</cNaoNIF>', $foreign->dps_xml);
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
