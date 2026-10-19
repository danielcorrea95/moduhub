<?php

namespace Tests\Feature\Invoices;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Invoices\Mail\InvoiceMail;
use App\Modules\Invoices\Models\Invoice;
use App\Platform\Contracts\ApiKeyServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\InteractsWithInvoices;
use Tests\TestCase;

class InvoiceDefaultsAndDeliveryTest extends TestCase
{
    use InteractsWithInvoices, RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->owner] = $this->makeInvoicesTenant();
        Mail::fake();
    }

    public function test_invoice_inherits_the_issuer_default_service_and_taxes(): void
    {
        $this->configureIssuer($this->tenant, [
            'default_service' => ['national_tax_code' => '080101', 'municipal_tax_code' => '001', 'nbs_code' => null, 'description' => 'Mensalidade escolar'],
            'default_approximate_taxes' => ['federal' => '4.20', 'state' => '0', 'municipal' => '2.00'],
        ]);

        $this->withToken($this->key())
            ->postJson('/api/v1/invoices', [
                'external_id' => 'mensalidade-10',
                'taker' => ['document' => '529.982.247-25', 'name' => 'Maria Responsável'],
                'values' => ['amount' => '850.00'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.service.national_tax_code', '080101')
            ->assertJsonPath('data.service.municipal_tax_code', '001')
            ->assertJsonPath('data.service.description', 'Mensalidade escolar')
            ->assertJsonPath('data.values.approximate_taxes.federal', '4.20');

        $xml = $this->sefin()->requests[0]['xml'];
        $this->assertStringContainsString('<cTribNac>080101</cTribNac><cTribMun>001</cTribMun>', $xml);
        $this->assertStringContainsString('<pTotTribFed>4.20</pTotTribFed>', $xml);
    }

    public function test_the_invoice_values_win_over_the_defaults(): void
    {
        $this->configureIssuer($this->tenant, ['default_service' => ['national_tax_code' => '080101', 'municipal_tax_code' => '001', 'description' => 'Mensalidade escolar']]);

        $this->withToken($this->key())
            ->postJson('/api/v1/invoices', $this->invoicePayload())
            ->assertCreated()
            ->assertJsonPath('data.service.national_tax_code', '010701')
            ->assertJsonPath('data.service.municipal_tax_code', null);
    }

    public function test_without_code_or_default_the_issue_is_refused(): void
    {
        $this->configureIssuer($this->tenant);

        $this->withToken($this->key())
            ->postJson('/api/v1/invoices', $this->invoicePayload(['service' => ['national_tax_code' => null, 'description' => null]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service.national_tax_code']);

        $this->assertSame(0, Invoice::withoutGlobalScopes()->count());
    }

    public function test_issued_invoice_is_emailed_to_the_taker_with_danfse_and_xml(): void
    {
        $this->configureIssuer($this->tenant);

        $id = $this->withToken($this->key())->postJson('/api/v1/invoices', $this->invoicePayload())->json('data.id');

        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) {
            $attachments = collect($mail->attachments())->map(fn ($a) => $a->as)->all();

            return $mail->hasTo('financeiro@cliente.test')
                && $mail->kind === InvoiceMail::KIND_ISSUED
                && count($attachments) === 2
                && str_ends_with($attachments[0], '.pdf')
                && str_ends_with($attachments[1], '.xml');
        });

        $invoice = Invoice::withoutGlobalScopes()->findOrFail($id);
        $this->assertNotNull($invoice->taker_emailed_at);

        $rendered = (new InvoiceMail($invoice, $this->tenant))->render();
        $this->assertStringContainsString('Cliente Exemplo S.A.', $rendered);
        $this->assertStringContainsString('R$ 1.500,00', $rendered);
        $this->assertStringContainsString('sem validade fiscal', $rendered);
    }

    public function test_no_email_when_disabled_and_cancellation_notice_when_sent_before(): void
    {
        $this->configureIssuer($this->tenant, ['email_taker' => false]);
        $key = $this->key();

        $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload())->assertCreated();
        Mail::assertNothingSent();

        $id = $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload(['external_id' => 'pedido-2', 'notify_taker' => true]))
            ->assertJsonPath('data.delivery.notify_taker', true)
            ->json('data.id');
        Mail::assertSent(InvoiceMail::class, 1);

        $this->withToken($key)->postJson("/api/v1/invoices/{$id}/cancel", ['reason_code' => 1, 'reason' => 'Valor do serviço errado'])->assertOk();
        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail) => $mail->kind === InvoiceMail::KIND_CANCELLED);
    }

    public function test_resend_to_other_addresses(): void
    {
        $this->configureIssuer($this->tenant, ['email_taker' => false]);
        $key = $this->key();
        $id = $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload())->json('data.id');

        $this->withToken($key)
            ->postJson("/api/v1/invoices/{$id}/email", ['emails' => ['pai@familia.test', 'mae@familia.test']])
            ->assertStatus(202)
            ->assertJsonPath('data.queued_to', ['pai@familia.test', 'mae@familia.test']);
        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail) => $mail->hasTo('pai@familia.test') && $mail->hasTo('mae@familia.test'));

        $this->withToken($key)->postJson("/api/v1/invoices/{$id}/email", ['emails' => ['não-é-email']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('emails.0');

        $this->actingAs($this->owner)
            ->post(route('invoices.email', $id), ['emails' => 'secretaria@escola.test, financeiro@escola.test'])
            ->assertSessionHas('success', 'Nota enviada para a fila de e-mail: secretaria@escola.test, financeiro@escola.test.');
    }

    private function key(): string
    {
        return app(ApiKeyServiceContract::class)->generate($this->tenant, $this->owner, 'Teste', ['invoices:*'])['plaintext'];
    }
}
