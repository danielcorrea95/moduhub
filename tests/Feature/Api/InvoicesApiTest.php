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

    public function test_exports_to_a_foreign_taker_through_an_intermediary(): void
    {
        $this->configureIssuer($this->tenant);
        $payload = $this->invoicePayload([
            'external_id' => 'pedido-export',
            'taker' => [
                'document' => null,
                'nif' => 'DE123456789',
                'name' => 'Müller GmbH',
                'phone' => null,
                'address' => [
                    'country' => 'de', 'postal_code' => '10115', 'street' => 'Invalidenstraße', 'number' => '117',
                    'complement' => null, 'district' => 'Mitte', 'city_code' => null, 'city' => 'Berlin', 'state' => 'Berlin',
                ],
            ],
            'intermediary' => ['document' => '529.982.247-25', 'name' => 'Representante Comercial'],
            'service' => [
                'result_country' => 'DE',
                'foreign_trade' => ['provision_mode' => '1', 'currency' => '978', 'foreign_amount' => '250.00'],
            ],
            'values' => [
                'iss_taxation' => Invoice::ISS_EXPORT,
                'iss_withheld' => true,
                'iss_withholder' => 'intermediary',
                'discounts' => ['unconditional' => '100.00', 'conditional' => ''],
                'deduction' => ['amount' => '200.00'],
            ],
        ]);

        $this->withToken($this->key(['invoices:*']))
            ->postJson('/api/v1/invoices', $payload)
            ->assertCreated()
            ->assertJsonPath('data.taker.nif', 'DE123456789')
            ->assertJsonPath('data.taker.address.country', 'DE')
            ->assertJsonPath('data.intermediary.document', '52998224725')
            ->assertJsonPath('data.values.discounts.unconditional', '100.00')
            ->assertJsonPath('data.values.discounts.conditional', null)
            ->assertJsonPath('data.values.deduction.amount', '200.00')
            ->assertJsonPath('data.values.iss_withholder', 'intermediary');

        $invoice = Invoice::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('DE123456789', $invoice->taker_document);
        $this->assertStringContainsString('<tpRetISSQN>3</tpRetISSQN>', $invoice->dps_xml);
    }

    public function test_validates_foreign_parties_and_export_groups(): void
    {
        $this->configureIssuer($this->tenant);

        $this->withToken($this->key(['invoices:*']))
            ->postJson('/api/v1/invoices', $this->invoicePayload([
                'taker' => [
                    'document' => '11.222.333/0001-81',
                    'nif' => 'X1',
                    'address' => ['country' => 'US', 'city_code' => '4313409', 'city' => null],
                ],
                'values' => ['iss_taxation' => Invoice::ISS_EXPORT, 'iss_withheld' => true, 'iss_withholder' => 'intermediary', 'discounts' => ['unconditional' => '1500.00']],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'taker.document', 'taker.address.city_code', 'taker.address.city', 'taker.address.state',
                'service.result_country', 'service.foreign_trade', 'values.iss_withholder', 'values.discounts.unconditional',
            ]);

        $this->withToken($this->key(['invoices:*']))
            ->postJson('/api/v1/invoices', $this->invoicePayload([
                'taker' => ['document' => null],
                'values' => ['iss_taxation' => Invoice::ISS_IMMUNE, 'deduction' => ['percent' => 10, 'amount' => 5]],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['taker.document', 'values.immunity_type', 'values.deduction.percent']);
    }

    public function test_ibs_cbs_comes_from_the_issuer_defaults_or_the_invoice(): void
    {
        $this->configureIssuer($this->tenant, ['ibs_cbs_cst' => '000', 'ibs_cbs_class_code' => '000001', 'ibs_cbs_operation_code' => '100301']);
        $key = $this->key(['invoices:*']);

        $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload())
            ->assertCreated()
            ->assertJsonPath('data.values.ibs_cbs', ['cst' => '000', 'class_code' => '000001', 'operation_code' => '100301', 'final_consumer' => null]);
        $this->assertStringContainsString('<cIndOp>100301</cIndOp><indDest>0</indDest>', $this->sefin()->requests[0]['xml']);

        $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload([
            'external_id' => 'pedido-1002',
            'values' => ['ibs_cbs' => ['cst' => '200', 'class_code' => '200028', 'operation_code' => '100301', 'final_consumer' => true]],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.values.ibs_cbs.cst', '200');
        $this->assertStringContainsString('<indFinal>1</indFinal>', $this->sefin()->requests[1]['xml']);
        $this->assertStringContainsString('<CST>200</CST><cClassTrib>200028</cClassTrib>', $this->sefin()->requests[1]['xml']);

        $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload([
            'external_id' => 'pedido-1003',
            'values' => ['ibs_cbs' => ['cst' => '200', 'class_code' => '000001']],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['values.ibs_cbs.class_code', 'values.ibs_cbs.operation_code']);
    }

    public function test_without_ibs_cbs_codes_the_dps_has_no_group(): void
    {
        $this->configureIssuer($this->tenant);

        $this->withToken($this->key(['invoices:*']))->postJson('/api/v1/invoices', $this->invoicePayload())
            ->assertCreated()
            ->assertJsonPath('data.values.ibs_cbs', null);
        $this->assertStringNotContainsString('<IBSCBS>', $this->sefin()->requests[0]['xml']);
    }

    public function test_deduction_by_documents(): void
    {
        $this->configureIssuer($this->tenant);
        $key = $this->key(['invoices:*']);

        $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload([
            'values' => ['deduction' => ['documents' => [[
                'kind' => 'nfe', 'access_key' => '3526 1011 2223 3300 0181 5500 1000 0012 3410 0001 2345',
                'reason' => '2', 'issued_on' => '2026-10-01', 'deductible_amount' => '800.00', 'amount' => '600.00',
                'number' => '', 'city_code' => '', 'supplier' => ['document' => '', 'name' => ''],
            ]]]],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.values.deduction.documents.0.access_key', '35261011222333000181550010000012341000012345')
            ->assertJsonPath('data.values.deduction.documents.0.amount', '600.00')
            ->assertJsonMissingPath('data.values.deduction.documents.0.supplier');
        $this->assertStringContainsString('<vDedRed><documentos><docDedRed><chNFe>', $this->sefin()->requests[0]['xml']);

        $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload([
            'external_id' => 'pedido-1002',
            'values' => ['deduction' => ['amount' => '10.00', 'documents' => [
                ['kind' => 'municipal_nfse', 'number' => '12', 'reason' => '99', 'issued_on' => '2026-10-01', 'deductible_amount' => '100', 'amount' => '200'],
                ['kind' => 'nfse', 'access_key' => '123', 'model' => '1', 'reason' => '2', 'issued_on' => '2026-10-01', 'deductible_amount' => '2000', 'amount' => '1400'],
            ]]],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'values.deduction.amount',
                'values.deduction.documents.0.city_code',
                'values.deduction.documents.0.verification_code',
                'values.deduction.documents.0.reason_description',
                'values.deduction.documents.0.amount',
                'values.deduction.documents.1.access_key',
                'values.deduction.documents.1.model',
                'values.deduction.documents',
            ]);
    }

    public function test_federal_withholdings_are_computed_from_the_rates(): void
    {
        $this->configureIssuer($this->tenant);

        $this->withToken($this->key(['invoices:*']))
            ->postJson('/api/v1/invoices', $this->invoicePayload([
                'values' => ['federal_taxes' => [
                    'pis_cofins' => ['cst' => '1', 'pis_rate' => '0.65', 'cofins_rate' => '3', 'withholding' => '3'],
                    'irrf' => ['rate' => '1.5'],
                    'csll' => ['rate' => '1'],
                    'inss' => ['amount' => '165.00', 'rate' => ''],
                ]],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.values.federal_taxes.pis_cofins.cst', '01')
            ->assertJsonPath('data.values.federal_taxes.pis_cofins.base', '1500.00')
            ->assertJsonPath('data.values.federal_taxes.pis_cofins.pis_amount', '9.75')
            ->assertJsonPath('data.values.federal_taxes.pis_cofins.cofins_amount', '45.00')
            ->assertJsonPath('data.values.federal_taxes.irrf.amount', '22.50')
            ->assertJsonPath('data.values.federal_taxes.csll.amount', '15.00')
            ->assertJsonPath('data.values.federal_taxes.inss.amount', '165.00');

        $this->assertStringContainsString('<vRetIRRF>22.50</vRetIRRF>', $this->sefin()->requests[0]['xml']);

        $this->withToken($this->key(['invoices:*']))
            ->postJson('/api/v1/invoices', $this->invoicePayload([
                'external_id' => 'pedido-2',
                'values' => [
                    'federal_taxes' => ['pis_cofins' => ['cst' => '10'], 'irrf' => ['rate' => 1, 'amount' => 5]],
                    'municipal_benefit' => ['number' => '123'],
                    'iss_suspension' => ['type' => '3'],
                ],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'values.federal_taxes.pis_cofins.cst', 'values.federal_taxes.irrf.rate',
                'values.municipal_benefit.number', 'values.iss_suspension.type', 'values.iss_suspension.process_number',
            ]);
    }

    public function test_special_groups_through_the_api(): void
    {
        $this->configureIssuer($this->tenant, ['ibs_cbs_cst' => '000', 'ibs_cbs_class_code' => '000001', 'ibs_cbs_operation_code' => '100301']);
        $key = $this->key(['invoices:*']);

        $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload([
            'intermediary' => ['document' => '529.982.247-25', 'name' => 'Representante'],
            'service' => [
                'construction' => ['work_code' => 'OBRA-1', 'cib' => '', 'address' => ['postal_code' => '', 'street' => '']],
                'additional_info' => ['notes' => 'Aluno: Fulano', 'order_items' => []],
            ],
            'values' => [
                'intermediary_received' => '1600.00',
                'ibs_cbs' => [
                    'recipient' => ['document' => '11.222.333/0001-81', 'name' => 'Filial'],
                    'property' => ['address' => ['postal_code' => '01310-100', 'street' => 'Av. Paulista', 'number' => '1000', 'district' => 'Bela Vista']],
                    'reimbursements' => [['kind' => 'other_document', 'number' => 'R-1', 'description' => 'Recibo', 'issued_on' => '2026-10-01', 'competence_on' => '2026-10-01', 'type' => '1', 'amount' => '10']],
                ],
            ],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.service.construction', ['work_code' => 'OBRA-1'])
            ->assertJsonPath('data.service.additional_info', ['notes' => 'Aluno: Fulano'])
            ->assertJsonPath('data.values.intermediary_received', '1600.00')
            ->assertJsonPath('data.values.ibs_cbs.cst', '000')
            ->assertJsonPath('data.values.ibs_cbs.recipient.document', '11222333000181')
            ->assertJsonPath('data.values.ibs_cbs.reimbursements.0.type', '01');

        $xml = $this->sefin()->requests[0]['xml'];
        $this->assertStringContainsString('<obra><cObra>OBRA-1</cObra></obra><infoCompl><xInfComp>Aluno: Fulano</xInfComp></infoCompl>', $xml);
        $this->assertStringContainsString('<indDest>1</indDest><dest><CNPJ>11222333000181</CNPJ>', $xml);
        $this->assertStringContainsString('<imovel><end><CEP>01310100</CEP>', $xml);

        $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload([
            'external_id' => 'pedido-2',
            'service' => [
                'construction' => ['work_code' => 'X', 'cib' => 'ABC'],
                'event' => ['name' => 'Feira', 'starts_on' => '2026-11-03', 'ends_on' => '2026-11-01'],
            ],
            'values' => [
                'intermediary_received' => '10',
                'ibs_cbs' => ['reimbursements' => [['kind' => 'dfe', 'number' => '1', 'type' => '99', 'issued_on' => '2026-10-01', 'competence_on' => '2026-10-01', 'amount' => '5']]],
            ],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'service.construction.work_code', 'service.construction.cib',
                'service.event.ends_on', 'service.event.event_id',
                'values.intermediary_received',
                'values.ibs_cbs.reimbursements.0.dfe_type', 'values.ibs_cbs.reimbursements.0.access_key',
                'values.ibs_cbs.reimbursements.0.number', 'values.ibs_cbs.reimbursements.0.type_description',
            ]);
    }

    public function test_ibs_cbs_extras_need_the_classification_codes(): void
    {
        $this->configureIssuer($this->tenant);

        $this->withToken($this->key(['invoices:*']))->postJson('/api/v1/invoices', $this->invoicePayload([
            'values' => ['ibs_cbs' => ['government_entity' => '4']],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['values.ibs_cbs.cst']);
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

    public function test_replaces_an_issued_invoice_and_fires_the_replaced_webhook(): void
    {
        $fired = [];
        $this->mock(WebhookDispatcherContract::class)
            ->shouldReceive('fire')
            ->andReturnUsing(function (Tenant $tenant, string $event, array $payload) use (&$fired) {
                $fired[] = [$event, $payload];
            });
        $this->configureIssuer($this->tenant);
        $key = $this->key(['invoices:*']);
        $original = $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload())->json('data');

        $replacement = $this->withToken($key)
            ->postJson("/api/v1/invoices/{$original['id']}/replace", $this->invoicePayload([
                'taker' => ['name' => 'Cliente Exemplo Ltda'],
                'substitution' => ['reason_code' => '99', 'reason' => 'Razão social do tomador estava errada'],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.status', Invoice::STATUS_ISSUED)
            ->assertJsonPath('data.substitution.replaces_invoice_id', $original['id'])
            ->assertJsonPath('data.substitution.replaces_access_key', $original['access_key'])
            ->assertJsonPath('data.substitution.reason_code', '99')
            ->json('data');

        $this->withToken($key)->getJson("/api/v1/invoices/{$original['id']}")
            ->assertOk()
            ->assertJsonPath('data.status', Invoice::STATUS_REPLACED)
            ->assertJsonPath('data.replacement.invoice_id', $replacement['id'])
            ->assertJsonPath('data.replacement.access_key', $replacement['access_key']);

        $this->assertSame(['invoice.issued', 'invoice.issued', 'invoice.replaced'], array_column($fired, 0));
        $this->assertSame($original['id'], $fired[1][1]['replaces_invoice_id']);
        $this->assertSame($replacement['id'], $fired[2][1]['replaced_by_invoice_id']);

        $this->withToken($key)
            ->postJson("/api/v1/invoices/{$original['id']}/replace", $this->invoicePayload(['substitution' => ['reason_code' => '01']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('substitution');
    }

    private function key(array $scopes): string
    {
        return app(ApiKeyServiceContract::class)
            ->generate($this->tenant, $this->owner, 'Teste', $scopes)['plaintext'];
    }
}
