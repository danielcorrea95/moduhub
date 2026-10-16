<?php

namespace Tests\Feature\Signatures;

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Signatures\Models\Document;
use App\Modules\Signatures\Models\DocumentSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentListingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->tenant = $this->makeTenantWithSignatures('Acme');
        $this->tenant->users()->attach($this->user, ['role' => 'owner']);
    }

    public function test_lists_documents_with_signers_and_status_counts(): void
    {
        $doc = $this->makeDocument($this->tenant, 'Contrato Alfa', Document::STATUS_PARTIALLY_SIGNED);
        $this->makeSigner($doc, 'Lisiane Fonseca', DocumentSigner::STATUS_SIGNED, 1);
        $this->makeSigner($doc, 'Dionísio Hatzenberger', DocumentSigner::STATUS_PENDING, 2);
        $this->makeDocument($this->tenant, 'Contrato Beta', Document::STATUS_COMPLETED);
        $this->makeDocument($this->tenant, 'Contrato Gama', Document::STATUS_COMPLETED);

        $this->actingAs($this->user)
            ->get(route('signatures.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Signatures/Index')
                ->has('documents.data', 3)
                ->where('statusCounts.completed', 2)
                ->where('statusCounts.partially_signed', 1)
                ->where('filters.status', null)
                ->where('documents.data.2.name', 'Contrato Alfa')
                ->has('documents.data.2.signers', 2)
                ->where('documents.data.2.signers.0.name', 'Lisiane Fonseca')
                ->where('documents.data.2.signers.0.status', 'signed'));
    }

    public function test_in_progress_filter_groups_sent_and_partially_signed(): void
    {
        $this->makeDocument($this->tenant, 'Enviado', Document::STATUS_SENT);
        $this->makeDocument($this->tenant, 'Parcial', Document::STATUS_PARTIALLY_SIGNED);
        $this->makeDocument($this->tenant, 'Rascunho', Document::STATUS_DRAFT);

        $this->actingAs($this->user)
            ->get(route('signatures.index', ['status' => 'in_progress']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('documents.data', 2)
                ->where('filters.status', 'in_progress')
                // Contagens ignoram o filtro: alimentam os botões de todos os status.
                ->where('statusCounts.draft', 1));
    }

    public function test_search_matches_document_name_or_signer(): void
    {
        $this->makeDocument($this->tenant, 'Contrato Natália Machado', Document::STATUS_SENT);
        $bySigner = $this->makeDocument($this->tenant, 'Aditivo 2026', Document::STATUS_SENT);
        $this->makeSigner($bySigner, 'Natália Souza', DocumentSigner::STATUS_PENDING, 1);
        $this->makeDocument($this->tenant, 'Outro contrato', Document::STATUS_SENT);

        $this->actingAs($this->user)
            ->get(route('signatures.index', ['search' => 'natália']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('documents.data', 2)
                ->where('filters.search', 'natália'));
    }

    public function test_does_not_leak_documents_from_other_tenants(): void
    {
        $other = $this->makeTenantWithSignatures('Outra empresa');
        $this->makeDocument($other, 'Contrato alheio', Document::STATUS_COMPLETED);
        $this->makeDocument($this->tenant, 'Contrato próprio', Document::STATUS_COMPLETED);

        $this->actingAs($this->user)
            ->get(route('signatures.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('documents.data', 1)
                ->where('statusCounts.completed', 1));
    }

    public function test_rejects_unknown_status_filter(): void
    {
        $this->actingAs($this->user)
            ->get(route('signatures.index', ['status' => 'whatever']))
            ->assertSessionHasErrors('status');
    }

    private function makeTenantWithSignatures(string $name): Tenant
    {
        $tenant = Tenant::create(['name' => $name, 'slug' => Str::slug($name).'-'.Str::random(4)]);
        $module = Module::firstOrCreate(['key' => 'signatures'], ['name' => 'Assinatura de Documentos']);
        $tenant->modules()->attach($module, ['source' => 'manual', 'is_active' => true, 'activated_at' => now()]);

        return $tenant;
    }

    private function makeDocument(Tenant $tenant, string $name, string $status): Document
    {
        return Document::forceCreate([
            'tenant_id' => $tenant->getKey(),
            'creator_id' => $this->user->getKey(),
            'name' => $name,
            'status' => $status,
            'file_path' => 'documents/'.Str::ulid().'.pdf',
            'file_hash' => hash('sha256', $name),
            'file_size' => 1024,
            'page_count' => 1,
        ]);
    }

    private function makeSigner(Document $document, string $name, string $status, int $order): DocumentSigner
    {
        return DocumentSigner::forceCreate([
            'tenant_id' => $document->tenant_id,
            'document_id' => $document->getKey(),
            'order' => $order,
            'name' => $name,
            'email' => Str::slug($name).'@example.com',
            'status' => $status,
            'token' => Str::random(40),
        ]);
    }
}
