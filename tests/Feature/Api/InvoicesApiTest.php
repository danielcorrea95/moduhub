<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\Issuer;
use App\Platform\Contracts\ApiKeyServiceContract;
use App\Platform\Contracts\WebhookDispatcherContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithInvoices;
use Tests\TestCase;

class InvoicesApiTest extends TestCase
{
    use InteractsWithInvoices, RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->owner] = $this->makeInvoicesTenant();
    }

    public function test_issues_an_invoice_in_the_restricted_environment(): void
    {
        $this->configureIssuer($this->tenant);

        $response = $this->withToken($this->key(['invoices:*']))
            ->postJson('/api/v1/invoices', $this->invoicePayload())
            ->assertCreated()
            ->assertJsonPath('data.status', Invoice::STATUS_ISSUED)
            ->assertJsonPath('data.environment', Issuer::ENV_RESTRICTED)
            ->assertJsonPath('data.external_id', 'pedido-1001')
            ->assertJsonPath('data.dps.number', 1)
            ->assertJsonPath('data.taker.document', '11222333000181')
            ->assertJsonPath('data.service.national_tax_code', '010701')
            ->assertJsonPath('data.values.amount', '1500.00')
            ->assertJsonPath('data.has_xml', true)
            ->assertJsonMissingPath('data.nfse_xml');

        $this->assertMatchesRegularExpression('/^\d{50}$/', $response->json('data.access_key'));
        $this->assertSame('1', $response->json('data.nfse_number'));
        $this->assertSame(2, Issuer::withoutGlobalScopes()->value('next_dps_number'));

        $sent = $this->sefin()->requests[0];
        $this->assertSame('/nfse', $sent['path']);
        $this->assertStringContainsString('<Signature xmlns="http://www.w3.org/2000/09/xmldsig#">', $sent['xml']);
    }

    public function test_same_external_id_returns_the_existing_invoice(): void
    {
        $this->configureIssuer($this->tenant);
        $key = $this->key(['invoices:*']);

        $first = $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload())->assertCreated();
        $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload())
            ->assertOk()
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertSame(1, Invoice::withoutGlobalScopes()->count());
        $this->assertCount(1, $this->sefin()->requests);
    }

    public function test_sefin_rejection_returns_422_with_the_reasons(): void
    {
        $this->configureIssuer($this->tenant);
        $this->sefin()->rejectNext([['code' => 'E0312', 'message' => 'Código de tributação nacional não administrado pelo município.']]);

        $this->withToken($this->key(['invoices:*']))
            ->postJson('/api/v1/invoices', $this->invoicePayload())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'A Sefin Nacional rejeitou a DPS.')
            ->assertJsonPath('data.status', Invoice::STATUS_REJECTED)
            ->assertJsonPath('data.rejection.0.code', 'E0312');

        // Uma nota rejeitada não segura o external_id: o CRM corrige e reenvia o mesmo pedido.
        $this->withToken($this->key(['invoices:*']))
            ->postJson('/api/v1/invoices', $this->invoicePayload())
            ->assertCreated()
            ->assertJsonPath('data.dps.number', 2);
    }

    public function test_without_answer_the_invoice_stays_pending_and_sync_recovers_it(): void
    {
        $this->configureIssuer($this->tenant);
        $key = $this->key(['invoices:*']);
        // A Sefin processou a DPS, mas a resposta se perdeu no caminho.
        $this->sefin()->loseNextResponse();

        $pending = $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload())
            ->assertAccepted()
            ->assertJsonPath('data.status', Invoice::STATUS_PENDING)
            ->assertJsonPath('data.access_key', null);
        $this->assertNotNull($pending->json('data.last_error'));

        $this->withToken($key)->postJson('/api/v1/invoices/'.$pending->json('data.id').'/sync')
            ->assertOk()
            ->assertJsonPath('data.status', Invoice::STATUS_ISSUED)
            ->assertJsonPath('data.last_error', null);

        // Consultou a DPS e buscou a nota; não reenviou.
        $paths = array_column($this->sefin()->requests, 'path');
        $this->assertSame(1, count(array_filter($paths, fn ($p) => $p === '/nfse')));
        $this->assertContains('/dps/'.$pending->json('data.dps.id'), $paths);
    }

    public function test_sync_resends_when_the_dps_never_arrived(): void
    {
        $this->configureIssuer($this->tenant);
        $key = $this->key(['invoices:*']);
        $this->sefin()->failNext();

        $pending = $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload())->assertAccepted();

        $this->withToken($key)->postJson('/api/v1/invoices/'.$pending->json('data.id').'/sync')
            ->assertOk()
            ->assertJsonPath('data.status', Invoice::STATUS_ISSUED)
            ->assertJsonPath('data.dps.number', 1);
    }

    public function test_cancels_an_issued_invoice(): void
    {
        $this->configureIssuer($this->tenant);
        $key = $this->key(['invoices:*']);
        $id = $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload())->json('data.id');
        $accessKey = Invoice::withoutGlobalScopes()->find($id)->access_key;

        $this->withToken($key)
            ->postJson("/api/v1/invoices/{$id}/cancel", ['reason_code' => Invoice::CANCEL_ISSUE_ERROR, 'reason' => 'curto'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->withToken($key)
            ->postJson("/api/v1/invoices/{$id}/cancel", ['reason_code' => Invoice::CANCEL_ISSUE_ERROR, 'reason' => 'Valor do serviço informado errado'])
            ->assertOk()
            ->assertJsonPath('data.status', Invoice::STATUS_CANCELLED)
            ->assertJsonPath('data.cancellation.reason_code', Invoice::CANCEL_ISSUE_ERROR);

        $event = collect($this->sefin()->requests)->firstWhere('path', "/nfse/{$accessKey}/eventos");
        $this->assertStringContainsString('<cMotivo>1</cMotivo>', $event['xml']);

        $this->withToken($key)
            ->postJson("/api/v1/invoices/{$id}/cancel", ['reason_code' => Invoice::CANCEL_OTHER, 'reason' => 'Tentando cancelar de novo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cancel');
    }

    public function test_sefin_refusing_the_cancellation_keeps_the_invoice_issued(): void
    {
        $this->configureIssuer($this->tenant);
        $key = $this->key(['invoices:*']);
        $id = $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload())->json('data.id');
        $this->sefin()->rejectNext([['code' => 'E1235', 'message' => 'Prazo de cancelamento expirado.']]);

        $response = $this->withToken($key)
            ->postJson("/api/v1/invoices/{$id}/cancel", ['reason_code' => Invoice::CANCEL_OTHER, 'reason' => 'Cliente desistiu do contrato'])
            ->assertUnprocessable();

        $this->assertStringContainsString('E1235', $response->json('errors.cancel.0'));
        $this->assertSame(Invoice::STATUS_ISSUED, Invoice::withoutGlobalScopes()->find($id)->status);
    }

    public function test_downloads_the_nfse_xml_and_lists_invoices(): void
    {
        $this->configureIssuer($this->tenant);
        $key = $this->key(['invoices:*']);
        $id = $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload())->json('data.id');

        $xml = $this->withToken($key)->get("/api/v1/invoices/{$id}/xml")->assertOk();
        $this->assertStringContainsString('<NFSe', $xml->getContent());

        $this->withToken($this->key(['invoices:read']))
            ->getJson('/api/v1/invoices?external_id=pedido-1001')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);
    }

    public function test_downloads_the_danfse_pdf(): void
    {
        $this->configureIssuer($this->tenant);
        $key = $this->key(['invoices:*']);
        $id = $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload())->json('data.id');

        $pdf = $this->withToken($this->key(['invoices:read']))->get("/api/v1/invoices/{$id}/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->sefin()->rejectNext([['code' => 'E0312', 'message' => 'Código não administrado.']]);
        $rejected = $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload(['external_id' => 'outro']))->json('data.id');
        $this->withToken($key)->get("/api/v1/invoices/{$rejected}/pdf")->assertNotFound();
    }

    public function test_requires_a_ready_issuer(): void
    {
        $key = $this->key(['invoices:*']);

        $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('issuer');

        $this->configureIssuer($this->tenant, withCertificate: false);

        $response = $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload())->assertUnprocessable();
        $this->assertStringContainsString('certificado', $response->json('errors.issuer.0'));
        $this->assertSame(0, Invoice::withoutGlobalScopes()->count());
    }

    public function test_validates_the_payload(): void
    {
        $this->configureIssuer($this->tenant);

        $this->withToken($this->key(['invoices:*']))
            ->postJson('/api/v1/invoices', $this->invoicePayload([
                'taker' => ['document' => '123.456.789-00', 'unknown' => 'x'],
                'service' => ['national_tax_code' => '1701'],
                'values' => ['amount' => 0, 'iss_rate' => 12],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['taker', 'taker.document', 'service.national_tax_code', 'values.amount', 'values.iss_rate']);

        $this->assertSame(1, Issuer::withoutGlobalScopes()->value('next_dps_number'), 'Erro de validação não consome número de DPS.');
    }

    public function test_read_only_key_cannot_issue(): void
    {
        $this->withToken($this->key(['invoices:read']))
            ->postJson('/api/v1/invoices', $this->invoicePayload())
            ->assertForbidden()
            ->assertJsonPath('required_scope', 'invoices:write');
    }

    public function test_cannot_see_invoices_of_another_tenant(): void
    {
        $this->configureIssuer($this->tenant);
        $id = $this->withToken($this->key(['invoices:*']))->postJson('/api/v1/invoices', $this->invoicePayload())->json('data.id');

        [$other, $otherOwner] = $this->makeInvoicesTenant('Outra');
        $otherKey = app(ApiKeyServiceContract::class)->generate($other, $otherOwner, 'Teste', ['*'])['plaintext'];

        $this->withToken($otherKey)->getJson("/api/v1/invoices/{$id}")->assertNotFound();
        $this->withToken($otherKey)->postJson("/api/v1/invoices/{$id}/cancel", ['reason_code' => 9, 'reason' => 'Tentativa de outro tenant'])->assertNotFound();
    }

    public function test_fires_webhooks_for_issued_rejected_and_cancelled(): void
    {
        $fired = [];
        $this->mock(WebhookDispatcherContract::class)
            ->shouldReceive('fire')
            ->andReturnUsing(function (Tenant $tenant, string $event, array $payload) use (&$fired) {
                $fired[] = [$tenant->getKey(), $event, $payload];
            });
        $this->configureIssuer($this->tenant);
        $key = $this->key(['invoices:*']);

        $this->sefin()->rejectNext([['code' => 'E0001', 'message' => 'Erro qualquer']]);
        $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload())->assertUnprocessable();
        $id = $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload())->json('data.id');
        $this->withToken($key)->postJson("/api/v1/invoices/{$id}/cancel", ['reason_code' => 2, 'reason' => 'Serviço não foi prestado'])->assertOk();

        $this->assertSame(['invoice.rejected', 'invoice.issued', 'invoice.cancelled'], array_column($fired, 1));
        $this->assertSame($this->tenant->getKey(), $fired[1][0]);
        $this->assertSame('pedido-1001', $fired[1][2]['external_id']);
        $this->assertSame('E0001', $fired[0][2]['errors'][0]['code']);
        $this->assertSame(2, $fired[2][2]['cancel_reason_code']);
    }

    private function key(array $scopes): string
    {
        return app(ApiKeyServiceContract::class)
            ->generate($this->tenant, $this->owner, 'Teste', $scopes)['plaintext'];
    }
}
