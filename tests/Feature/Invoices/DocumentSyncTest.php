<?php

namespace Tests\Feature\Invoices;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Invoices\Contracts\DfeDistributionContract;
use App\Modules\Invoices\Models\DistributedDocument;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Models\ReceivedInvoice;
use App\Modules\Invoices\Services\Adn\DistributedDfe;
use App\Modules\Invoices\Services\Adn\FakeDfeDistribution;
use App\Modules\Invoices\Services\Nfse\DpsBuilder;
use App\Modules\Invoices\Services\Nfse\EventRequestBuilder;
use App\Modules\Invoices\Services\Nfse\XmlSchemaValidator;
use App\Platform\Contracts\ApiKeyServiceContract;
use App\Platform\Contracts\WebhookDispatcherContract;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\InteractsWithInvoices;
use Tests\TestCase;

/**
 * Distribuição do ADN: notas recebidas, eventos de terceiros nas nossas notas, análise fiscal e manifestação.
 */
class DocumentSyncTest extends TestCase
{
    use InteractsWithInvoices, RefreshDatabase;

    private const SUPPLIER_CNPJ = '11222333000181';

    private const RECEIVED_KEY = '35503082211222333000181000000000000726108607888209';

    private Tenant $tenant;

    private User $owner;

    /** @var list<array{0: string, 1: array}> */
    private array $fired = [];

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->owner] = $this->makeInvoicesTenant();
        Mail::fake();
        $this->mock(WebhookDispatcherContract::class)
            ->shouldReceive('fire')
            ->andReturnUsing(function (Tenant $tenant, string $event, array $payload) {
                $this->fired[] = [$event, $payload];
            });
    }

    public function test_received_invoice_arrives_and_a_second_sync_does_not_duplicate(): void
    {
        $issuer = $this->configureIssuer($this->tenant);
        $this->adn()->push($this->dfe('NFSE', null, $this->receivedNfseXml()));

        $this->artisan('invoices:sync-documents')->assertSuccessful();
        $this->artisan('invoices:sync-documents')->assertSuccessful();

        $received = ReceivedInvoice::withoutGlobalScopes()->sole();
        $this->assertSame(ReceivedInvoice::ROLE_TAKER, $received->role);
        $this->assertSame(self::SUPPLIER_CNPJ, $received->provider_document);
        $this->assertSame('Fornecedor de Serviços Ltda', $received->provider_name);
        $this->assertSame(self::RECEIVED_KEY, $received->access_key);
        $this->assertSame('10.00', (string) $received->amount);
        $this->assertSame(1, DistributedDocument::withoutGlobalScopes()->count());
        $this->assertSame(['restricted' => 1], $issuer->fresh()->dfe_cursor);
        $this->assertSame([0, 1, 1], $this->adn()->requests, 'A segunda varredura continua do NSU salvo.');
        $this->assertSame(['invoice.received'], array_column($this->fired, 0));
    }

    public function test_events_from_third_parties_update_our_invoice(): void
    {
        $this->configureIssuer($this->tenant);
        $invoice = $this->issueInvoice();

        $this->adn()->push($this->dfe('EVENTO', 'REJEICAO_TOMADOR', $this->eventXml($invoice->access_key, '203206', '<cMotivo>5</cMotivo><xMotivo>Valor do serviço diferente do contratado</xMotivo>')));
        $this->artisan('invoices:sync-documents')->assertSuccessful();

        $invoice->refresh();
        $this->assertSame('rejected', $invoice->taker_manifestation['type']);
        $this->assertSame('taker', $invoice->taker_manifestation['by']);
        $this->assertSame('5', $invoice->taker_manifestation['reason_code']);
        $this->assertContains('invoice.taker_rejected', array_column($this->fired, 0));

        $this->adn()
            ->push($this->dfe('EVENTO', 'BLOQUEIO_POR_OFICIO', $this->eventXml($invoice->access_key, '305102', '')))
            ->push($this->dfe('EVENTO', 'CANCELAMENTO_POR_OFICIO', $this->eventXml($invoice->access_key, '305101', '<cMotivo>9</cMotivo><xMotivo>Cancelada pela prefeitura</xMotivo>')));
        $this->artisan('invoices:sync-documents')->assertSuccessful();

        $invoice->refresh();
        $this->assertTrue($invoice->isCancelled());
        $this->assertNotNull($invoice->blocked_at);
        $this->assertSame('Cancelada pela prefeitura', $invoice->cancel_reason);

        $this->actingAs($this->owner)
            ->get(route('invoices.show', $invoice))
            ->assertInertia(fn (Assert $page) => $page
                ->has('events', 3)
                ->where('events.0.label', 'Rejeição do tomador')
                ->where('events.2.label', 'Cancelamento por ofício'));
    }

    public function test_cancellation_review_is_requested_and_decided_by_the_tax_office(): void
    {
        $this->configureIssuer($this->tenant);
        $invoice = $this->issueInvoice();

        $this->actingAs($this->owner)
            ->post(route('invoices.cancellation-review', $invoice), ['reason_code' => 2, 'reason' => 'Serviço não foi prestado ao cliente'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $sent = collect($this->sefin()->requests)->last();
        $this->assertStringContainsString('<e101103><xDesc>Solicitação de Análise Fiscal para Cancelamento de NFS-e</xDesc><cMotivo>2</cMotivo>', $sent['xml']);
        $this->assertSame('requested', $invoice->fresh()->cancellation_review['status']);

        $this->actingAs($this->owner)
            ->post(route('invoices.cancellation-review', $invoice), ['reason_code' => 2, 'reason' => 'Serviço não foi prestado ao cliente'])
            ->assertSessionHasErrors('review');

        $this->adn()->push($this->dfe('EVENTO', 'CANCELAMENTO_DEFERIDO_ANALISE_FISCAL', $this->eventXml($invoice->access_key, '105104', '')));
        $this->artisan('invoices:sync-documents')->assertSuccessful();

        $invoice->refresh();
        $this->assertTrue($invoice->isCancelled());
        $this->assertSame('approved', $invoice->cancellation_review['status']);
    }

    public function test_taker_manifests_a_received_invoice(): void
    {
        $this->configureIssuer($this->tenant);
        $this->adn()->push($this->dfe('NFSE', null, $this->receivedNfseXml()));
        $this->artisan('invoices:sync-documents')->assertSuccessful();
        $received = ReceivedInvoice::withoutGlobalScopes()->sole();

        $this->actingAs($this->owner)
            ->get(route('invoices.received.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Invoices/Received')
                ->has('invoices.data', 1)
                ->where('invoices.data.0.provider.name', 'Fornecedor de Serviços Ltda')
                ->where('canManifest', true));

        $this->actingAs($this->owner)
            ->post(route('invoices.received.manifest', $received), ['decision' => 'reject'])
            ->assertSessionHasErrors('reason_code');

        $this->actingAs($this->owner)
            ->post(route('invoices.received.manifest', $received), ['decision' => 'reject', 'reason_code' => '3', 'reason' => 'O serviço não foi prestado'])
            ->assertSessionHasNoErrors();

        $sent = collect($this->sefin()->requests)->last();
        $this->assertSame('/nfse/'.self::RECEIVED_KEY.'/eventos', $sent['path']);
        $this->assertStringContainsString('<e203206><xDesc>Manifestação de NFS-e - Rejeição do Tomador</xDesc><cMotivo>3</cMotivo>', $sent['xml']);
        $this->assertSame('rejected', $received->fresh()->manifestation['type']);

        $this->actingAs($this->owner)
            ->post(route('invoices.received.manifest', $received), ['decision' => 'confirm'])
            ->assertSessionHasErrors('manifestation');

        $this->actingAs($this->owner)->get(route('invoices.received.pdf', $received))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->owner)->get(route('invoices.received.xml', $received))->assertOk();
    }

    public function test_api_lists_received_invoices_and_member_cannot_manifest(): void
    {
        $this->configureIssuer($this->tenant);
        $this->adn()->push($this->dfe('NFSE', null, $this->receivedNfseXml()));
        $key = app(ApiKeyServiceContract::class)->generate($this->tenant, $this->owner, 'Teste', ['invoices:*'])['plaintext'];

        $this->withToken($key)->postJson('/api/v1/invoices/received/sync')
            ->assertOk()
            ->assertJsonPath('data.received', 1);

        $id = $this->withToken($key)->getJson('/api/v1/invoices/received?role=taker')
            ->assertOk()
            ->assertJsonPath('data.0.access_key', self::RECEIVED_KEY)
            ->assertJsonPath('data.0.manifestation', null)
            ->json('data.0.id');

        $this->withToken($key)->postJson("/api/v1/invoices/received/{$id}/manifest", ['decision' => 'confirm'])
            ->assertOk()
            ->assertJsonPath('data.manifestation.type', 'confirmed');
        $this->assertStringContainsString('<e203202>', collect($this->sefin()->requests)->last()['xml']);

        $member = User::factory()->create();
        $this->tenant->users()->attach($member, ['role' => 'member']);
        $this->actingAs($member)
            ->post(route('invoices.received.manifest', $id), ['decision' => 'confirm'])
            ->assertForbidden();
    }

    public function test_adn_failure_keeps_the_cursor(): void
    {
        $issuer = $this->configureIssuer($this->tenant);
        $this->adn()->failNext('E9999 Serviço indisponível');

        $this->artisan('invoices:sync-documents')->expectsOutputToContain('E9999 Serviço indisponível')->assertSuccessful();
        $this->assertNull($issuer->fresh()->dfe_cursor);
    }

    public function test_every_event_request_follows_the_schema(): void
    {
        $issuer = new Issuer(['cnpj' => self::ISSUER_CNPJ, 'environment' => Issuer::ENV_RESTRICTED]);
        $builder = app(EventRequestBuilder::class);

        foreach ([
            [EventRequestBuilder::CANCELLATION_REVIEW, '9', 'Pedido fora do prazo de cancelamento'],
            [EventRequestBuilder::TAKER_CONFIRMATION, null, null],
            [EventRequestBuilder::INTERMEDIARY_CONFIRMATION, null, null],
            [EventRequestBuilder::PROVIDER_CONFIRMATION, null, null],
            [EventRequestBuilder::TAKER_REJECTION, '1', null],
            [EventRequestBuilder::INTERMEDIARY_REJECTION, '9', 'Serviço não contratado por nós'],
            [EventRequestBuilder::PROVIDER_REJECTION, '2', null],
        ] as [$event, $code, $reason]) {
            $doc = $builder->build(str_repeat('1', 50), $issuer, $event, $code, $reason);
            // A assinatura é opcional no XSD; o que importa aqui é a estrutura de cada evento.
            app(XmlSchemaValidator::class)->assertValid($doc, 'pedRegEvento_v1.01.xsd');
            $this->assertStringContainsString('Id="PRE'.str_repeat('1', 50).$event.'"', $doc->saveXML());
        }
    }

    private function issueInvoice(): Invoice
    {
        $key = app(ApiKeyServiceContract::class)->generate($this->tenant, $this->owner, 'Teste', ['invoices:*'])['plaintext'];
        $id = $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload())->assertCreated()->json('data.id');

        return Invoice::withoutGlobalScopes()->findOrFail($id);
    }

    private function adn(): FakeDfeDistribution
    {
        return app(DfeDistributionContract::class);
    }

    private function dfe(string $type, ?string $eventType, string $xml): DistributedDfe
    {
        preg_match('/<chNFSe>(\d{50})<\/chNFSe>|Id="NFS(\d{50})"/', $xml, $m);

        return new DistributedDfe($this->adn()->nextNsu(), $m[1] ?: $m[2], $type, $eventType, $xml, CarbonImmutable::now());
    }

    /** NFS-e de um fornecedor contra o emitente do teste (o fixture com prestador e tomador trocados). */
    private function receivedNfseXml(): string
    {
        $xml = (string) file_get_contents(base_path('tests/Fixtures/nfse/nfse-sp-restrita.xml'));
        $xml = str_replace(self::ISSUER_CNPJ, self::SUPPLIER_CNPJ, $xml);
        $xml = str_replace('ACME SOFTWARE LTDA', 'Fornecedor de Serviços Ltda', $xml);
        $xml = str_replace('<CPF>52998224725</CPF>', '<CNPJ>'.self::ISSUER_CNPJ.'</CNPJ>', $xml);

        return preg_replace('/Id="NFS\d{50}"/', 'Id="NFS'.self::RECEIVED_KEY.'"', $xml);
    }

    private function eventXml(string $accessKey, string $code, string $fields): string
    {
        $ns = DpsBuilder::NS;

        return '<?xml version="1.0" encoding="utf-8"?>'
            ."<evento versao=\"1.01\" xmlns=\"{$ns}\"><infEvento Id=\"EVT{$accessKey}{$code}001\">"
            .'<verAplic>SefinNacional_1.6.0</verAplic><ambGer>2</ambGer><nSeqEvento>1</nSeqEvento>'
            .'<dhProc>2026-10-19T10:00:00-03:00</dhProc><nDFSe>0</nDFSe>'
            ."<pedRegEvento versao=\"1.01\"><infPedReg Id=\"PRE{$accessKey}{$code}\"><tpAmb>2</tpAmb><verAplic>Outro 1.0</verAplic>"
            .'<dhEvento>2026-10-19T09:59:00-03:00</dhEvento><CNPJAutor>'.self::SUPPLIER_CNPJ."</CNPJAutor><chNFSe>{$accessKey}</chNFSe>"
            ."<e{$code}><xDesc>Evento</xDesc>{$fields}</e{$code}></infPedReg></pedRegEvento></infEvento></evento>";
    }
}
