<?php

namespace Tests\Feature\Invoices;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Invoices\Models\Invoice;
use App\Platform\Contracts\ApiKeyServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InteractsWithInvoices;
use Tests\TestCase;

class XmlArchiveTest extends TestCase
{
    use InteractsWithInvoices, RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->owner] = $this->makeInvoicesTenant();
    }

    public function test_issued_invoice_keeps_only_the_paths_in_the_database(): void
    {
        $this->configureIssuer($this->tenant);
        $id = $this->withToken($this->key(['invoices:*']))
            ->postJson('/api/v1/invoices', $this->invoicePayload())
            ->assertCreated()
            ->json('data.id');

        $row = DB::table('invoices')->where('id', $id)->first();
        $prefix = $this->tenant->getKey().'/invoices/'.now()->format('Y/m').'/';

        $this->assertStringStartsWith($prefix, $row->dps_xml_path);
        $this->assertStringEndsWith('-dps-xml.xml', $row->dps_xml_path);
        $this->assertStringEndsWith('-nfse-xml.xml', $row->nfse_xml_path);
        $this->assertNull($row->cancellation_xml_path);

        $disk = Storage::disk('fiscal');
        $this->assertStringContainsString('<DPS', $disk->get($row->dps_xml_path));
        $this->assertStringContainsString('<NFSe', $disk->get($row->nfse_xml_path));

        $invoice = Invoice::withoutGlobalScopes()->find($id);
        $this->assertSame($disk->get($row->nfse_xml_path), $invoice->nfse_xml);
        $this->assertTrue($invoice->hasNfseXml());
        $this->assertArrayNotHasKey('nfse_xml_path', $invoice->toArray());
    }

    public function test_cancellation_archives_the_event_without_touching_the_nfse(): void
    {
        $this->configureIssuer($this->tenant);
        $key = $this->key(['invoices:*']);
        $id = $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload())->json('data.id');
        $nfsePath = DB::table('invoices')->where('id', $id)->value('nfse_xml_path');

        $this->withToken($key)
            ->postJson("/api/v1/invoices/{$id}/cancel", ['reason_code' => Invoice::CANCEL_ISSUE_ERROR, 'reason' => 'Valor do serviço informado errado'])
            ->assertOk();

        $row = DB::table('invoices')->where('id', $id)->first();
        $this->assertSame($nfsePath, $row->nfse_xml_path);
        $this->assertNotNull($row->cancellation_xml_path);
        $this->assertTrue(Storage::disk('fiscal')->exists($row->cancellation_xml_path));
    }

    public function test_failed_upload_aborts_the_save(): void
    {
        $this->configureIssuer($this->tenant);
        $id = $this->withToken($this->key(['invoices:*']))->postJson('/api/v1/invoices', $this->invoicePayload())->json('data.id');
        $invoice = Invoice::withoutGlobalScopes()->find($id);
        $before = DB::table('invoices')->where('id', $id)->value('cancellation_xml_path');

        Storage::shouldReceive('disk')->with('fiscal')->andThrow(new \RuntimeException('S3 fora do ar'));

        try {
            $invoice->forceFill(['cancellation_xml' => '<evento/>', 'status' => Invoice::STATUS_CANCELLED])->save();
            $this->fail('O save deveria ter falhado junto com o upload.');
        } catch (\RuntimeException $e) {
            $this->assertSame('S3 fora do ar', $e->getMessage());
        }

        $this->assertSame($before, DB::table('invoices')->where('id', $id)->value('cancellation_xml_path'));
        $this->assertSame(Invoice::STATUS_ISSUED, DB::table('invoices')->where('id', $id)->value('status'));
    }

    private function key(array $scopes): string
    {
        return app(ApiKeyServiceContract::class)
            ->generate($this->tenant, $this->owner, 'Teste', $scopes)['plaintext'];
    }
}
