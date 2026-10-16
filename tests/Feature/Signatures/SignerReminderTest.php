<?php

namespace Tests\Feature\Signatures;

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Signatures\Mail\DocumentReadyMail;
use App\Modules\Signatures\Models\Document;
use App\Modules\Signatures\Models\DocumentEvent;
use App\Modules\Signatures\Models\DocumentSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SignerReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme-'.Str::random(4)]);
        $this->tenant->users()->attach($this->user, ['role' => 'owner']);
        $module = Module::firstOrCreate(['key' => 'signatures'], ['name' => 'Assinatura de Documentos']);
        $this->tenant->modules()->attach($module, ['source' => 'manual', 'is_active' => true, 'activated_at' => now()]);
    }

    public function test_show_exposes_sign_link_only_for_signers_who_can_sign_now(): void
    {
        $doc = $this->makeDocument(Document::STATUS_PARTIALLY_SIGNED, Document::SIGN_ORDER_SEQUENTIAL);
        $this->makeSigner($doc, 'Ana', DocumentSigner::STATUS_SIGNED, 1);
        $current = $this->makeSigner($doc, 'Bruno', DocumentSigner::STATUS_VIEWED, 2);
        $this->makeSigner($doc, 'Carla', DocumentSigner::STATUS_PENDING, 3);

        $this->actingAs($this->user)
            ->get(route('signatures.show', $doc))
            ->assertInertia(fn (Assert $page) => $page
                ->where('document.signers.0.sign_url', null)
                ->where('document.signers.1.sign_url', route('public-sign.show', $current->token))
                // Sequencial: Carla só recebe o link quando chegar a vez dela.
                ->where('document.signers.2.sign_url', null)
                ->missing('document.signers.0.token'));
    }

    public function test_draft_documents_never_expose_sign_links(): void
    {
        $doc = $this->makeDocument(Document::STATUS_DRAFT);
        $this->makeSigner($doc, 'Ana', DocumentSigner::STATUS_PENDING, 1);

        $this->actingAs($this->user)
            ->get(route('signatures.show', $doc))
            ->assertInertia(fn (Assert $page) => $page->where('document.signers.0.sign_url', null));
    }

    public function test_remind_resends_email_and_records_event(): void
    {
        Mail::fake();
        $doc = $this->makeDocument(Document::STATUS_SENT);
        $signer = $this->makeSigner($doc, 'Ana', DocumentSigner::STATUS_PENDING, 1);

        $this->actingAs($this->user)
            ->post(route('signatures.signers.remind', [$doc, $signer]))
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(DocumentReadyMail::class, fn ($mail) => $mail->hasTo($signer->email));
        $this->assertDatabaseHas('document_events', [
            'document_id' => $doc->getKey(),
            'signer_id' => $signer->getKey(),
            'type' => DocumentEvent::TYPE_REMINDED,
        ]);
    }

    public function test_cannot_remind_signer_who_already_signed(): void
    {
        Mail::fake();
        $doc = $this->makeDocument(Document::STATUS_PARTIALLY_SIGNED);
        $signer = $this->makeSigner($doc, 'Ana', DocumentSigner::STATUS_SIGNED, 1);

        $this->actingAs($this->user)
            ->post(route('signatures.signers.remind', [$doc, $signer]))
            ->assertSessionHasErrors('signer');

        Mail::assertNothingSent();
    }

    public function test_cannot_remind_on_draft_or_finalized_documents(): void
    {
        Mail::fake();
        foreach ([Document::STATUS_DRAFT, Document::STATUS_COMPLETED] as $status) {
            $doc = $this->makeDocument($status);
            $signer = $this->makeSigner($doc, 'Ana '.$status, DocumentSigner::STATUS_PENDING, 1);

            $this->actingAs($this->user)
                ->post(route('signatures.signers.remind', [$doc, $signer]))
                ->assertForbidden();
        }

        Mail::assertNothingSent();
    }

    private function makeDocument(string $status, string $order = Document::SIGN_ORDER_PARALLEL): Document
    {
        return Document::forceCreate([
            'tenant_id' => $this->tenant->getKey(),
            'creator_id' => $this->user->getKey(),
            'name' => 'Contrato '.Str::random(4),
            'status' => $status,
            'sign_order' => $order,
            'file_path' => 'documents/'.Str::ulid().'.pdf',
            'file_hash' => hash('sha256', Str::random()),
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
            'token' => Str::random(48),
        ]);
    }
}
