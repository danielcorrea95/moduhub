<?php

namespace Tests\Feature\Api;

use App\Jobs\DispatchWebhookJob;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Modules\Signatures\Mail\DocumentReadyMail;
use App\Modules\Signatures\Models\Document;
use App\Modules\Signatures\Models\DocumentSigner;
use App\Platform\Contracts\ApiKeyServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SignaturesApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documents');
        Mail::fake();
        Queue::fake();

        [$this->tenant, $this->owner] = $this->makeTenant('Acme');
    }

    public function test_requests_without_a_key_are_rejected(): void
    {
        $this->getJson('/api/v1/signatures/documents')->assertUnauthorized();
    }

    public function test_read_only_key_can_list_but_not_create(): void
    {
        $key = $this->key(['signatures:read']);

        $this->withToken($key)->getJson('/api/v1/signatures/documents')->assertOk();
        $this->withToken($key)
            ->postJson('/api/v1/signatures/documents', ['file_base64' => base64_encode($this->pdf()), 'name' => 'X'])
            ->assertForbidden()
            ->assertJsonPath('required_scope', 'signatures:write');
    }

    public function test_creates_document_with_signers_and_sends_in_one_call(): void
    {
        WebhookEndpoint::create([
            'tenant_id' => $this->tenant->getKey(),
            'url' => 'https://example.com/hook',
            'secret' => 'whsec_test',
            'events' => ['document.sent'],
            'is_active' => true,
        ]);

        $response = $this->withToken($this->key(['signatures:*']))
            ->post('/api/v1/signatures/documents', [
                'file' => UploadedFile::fake()->createWithContent('contrato.pdf', $this->pdf()),
                'name' => 'Contrato 0019',
                'external_id' => 'CRM-0019',
                'sign_order' => 'sequential',
                'send' => true,
                'signers' => [
                    ['name' => 'Lisiane Fonseca', 'role' => '  Responsável financeiro ', 'email' => 'lisiane@example.com', 'cnpj' => '12.345.678/0001-90'],
                    ['name' => 'Dionísio Hatzenberger', 'email' => 'dionisio@example.com'],
                ],
            ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.status', Document::STATUS_SENT)
            ->assertJsonPath('data.external_id', 'CRM-0019')
            ->assertJsonPath('data.signers.0.cnpj', '12345678000190')
            ->assertJsonPath('data.signers.0.role', 'Responsável financeiro')
            ->assertJsonPath('data.signers.1.role', null)
            ->assertJsonMissingPath('data.signers.0.token');

        // Sequencial: só o primeiro recebe link e e-mail agora.
        $this->assertNotNull($response->json('data.signers.0.sign_url'));
        $this->assertNull($response->json('data.signers.1.sign_url'));
        Mail::assertSent(DocumentReadyMail::class, 1);

        $this->assertDatabaseHas('webhook_deliveries', ['event' => 'document.sent']);
        Queue::assertPushed(DispatchWebhookJob::class);
    }

    public function test_creates_draft_from_base64(): void
    {
        $this->withToken($this->key(['signatures:write']))
            ->postJson('/api/v1/signatures/documents', [
                'file_base64' => 'data:application/pdf;base64,'.base64_encode($this->pdf()),
                'name' => 'Aditivo',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', Document::STATUS_DRAFT)
            ->assertJsonPath('data.page_count', 1);

        Mail::assertNothingSent();
    }

    public function test_rejects_base64_that_is_not_a_pdf(): void
    {
        $this->withToken($this->key(['signatures:write']))
            ->postJson('/api/v1/signatures/documents', ['file_base64' => base64_encode('hello'), 'name' => 'X'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file_base64');
    }

    public function test_failed_send_leaves_no_half_created_document(): void
    {
        // Enviar sem signatários viola a regra do envio: nada pode ficar gravado.
        $this->withToken($this->key(['signatures:write']))
            ->postJson('/api/v1/signatures/documents', [
                'file_base64' => base64_encode($this->pdf()),
                'name' => 'Inválido',
                'send' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('signers');

        $this->assertSame(0, Document::withoutGlobalScopes()->count());
        $this->assertEmpty(Storage::disk('documents')->allFiles());
    }

    public function test_lists_with_external_id_filter(): void
    {
        $this->makeDocument($this->tenant, ['external_id' => 'CRM-1']);
        $this->makeDocument($this->tenant, ['external_id' => 'CRM-2']);

        $this->withToken($this->key(['signatures:read']))
            ->getJson('/api/v1/signatures/documents?external_id=CRM-2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.external_id', 'CRM-2')
            ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'total']]);
    }

    public function test_cannot_reach_documents_of_another_tenant(): void
    {
        [$other] = $this->makeTenant('Outra');
        $foreign = $this->makeDocument($other);
        $key = $this->key(['*']);

        $this->withToken($key)->getJson("/api/v1/signatures/documents/{$foreign->getKey()}")->assertNotFound();
        $this->withToken($key)->deleteJson("/api/v1/signatures/documents/{$foreign->getKey()}")->assertNotFound();
        $this->assertDatabaseHas('documents', ['id' => $foreign->getKey(), 'deleted_at' => null]);
    }

    public function test_only_drafts_can_be_deleted(): void
    {
        $key = $this->key(['signatures:write']);
        $sent = $this->makeDocument($this->tenant, ['status' => Document::STATUS_SENT]);
        $draft = $this->makeDocument($this->tenant);

        $this->withToken($key)->deleteJson("/api/v1/signatures/documents/{$sent->getKey()}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document');
        $this->withToken($key)->deleteJson("/api/v1/signatures/documents/{$draft->getKey()}")->assertNoContent();
    }

    public function test_reminds_a_pending_signer(): void
    {
        $doc = $this->makeDocument($this->tenant, ['status' => Document::STATUS_SENT]);
        $signer = DocumentSigner::forceCreate([
            'tenant_id' => $this->tenant->getKey(),
            'document_id' => $doc->getKey(),
            'order' => 1,
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'status' => DocumentSigner::STATUS_PENDING,
            'token' => Str::random(48),
        ]);

        $this->withToken($this->key(['signatures:write']))
            ->postJson("/api/v1/signatures/documents/{$doc->getKey()}/signers/{$signer->getKey()}/remind")
            ->assertOk();

        Mail::assertSent(DocumentReadyMail::class, fn ($m) => $m->hasTo('ana@example.com'));
    }

    /** @return array{0: Tenant, 1: User} */
    private function makeTenant(string $name): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => $name, 'slug' => Str::slug($name).'-'.Str::random(4), 'status' => 'active']);
        $tenant->users()->attach($user, ['role' => 'owner']);
        $module = Module::firstOrCreate(['key' => 'signatures'], ['name' => 'Assinatura de Documentos']);
        $tenant->modules()->attach($module, ['source' => 'manual', 'is_active' => true, 'activated_at' => now()]);

        return [$tenant, $user];
    }

    private function key(array $scopes): string
    {
        return app(ApiKeyServiceContract::class)
            ->generate($this->tenant, $this->owner, 'Teste', $scopes)['plaintext'];
    }

    private function makeDocument(Tenant $tenant, array $attrs = []): Document
    {
        return Document::forceCreate(array_merge([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Contrato '.Str::random(4),
            'status' => Document::STATUS_DRAFT,
            'file_path' => 'x/'.Str::ulid().'.pdf',
            'file_hash' => str_repeat('a', 64),
            'file_size' => 10,
            'page_count' => 1,
        ], $attrs));
    }

    private function pdf(): string
    {
        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->Write(0, 'Contrato de teste');

        return $pdf->Output('', 'S');
    }
}
