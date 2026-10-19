<?php

namespace Tests\Feature\Signatures;

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Signatures\Contracts\DocumentSubmissionServiceContract;
use App\Modules\Signatures\Mail\DocumentCompletedMail;
use App\Modules\Signatures\Models\Document;
use App\Modules\Signatures\Models\DocumentEvent;
use App\Modules\Signatures\Models\DocumentSigner;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesPkcs12;
use Tests\TestCase;

class PlatformSealTest extends TestCase
{
    use CreatesPkcs12, RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private ?string $certificatePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Queue::fake();

        $this->owner = User::factory()->create();
        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme-'.Str::random(4), 'status' => 'active']);
        $this->tenant->users()->attach($this->owner, ['role' => 'owner']);
        $module = Module::firstOrCreate(['key' => 'signatures'], ['name' => 'Assinatura de Documentos']);
        $this->tenant->modules()->attach($module, ['source' => 'manual', 'is_active' => true, 'activated_at' => now()]);
    }

    protected function tearDown(): void
    {
        if ($this->certificatePath !== null) {
            @unlink($this->certificatePath);
        }

        parent::tearDown();
    }

    public function test_last_signature_seals_the_final_pdf_with_the_platform_certificate(): void
    {
        $this->configureSeal('MODUHUB TESTE LTDA:12345678000190');
        [$document, $first, $second] = $this->sentDocumentWithTwoSigners();

        $this->sign($first);
        $this->assertFalse($this->finalPdf($document->fresh())['signed'], 'Assinatura parcial não pode selar o PDF.');

        $this->sign($second);

        $document->refresh();
        $this->assertSame(Document::STATUS_COMPLETED, $document->status);
        $this->assertTrue($document->isSealed());
        $this->assertSame('MODUHUB TESTE LTDA:12345678000190', $document->seal_certificate_subject);

        $pdf = $this->finalPdf($document);
        $this->assertTrue($pdf['signed']);
        $this->assertPkcs7FitsInPdf($pdf['content']);
        $this->assertSame(hash('sha256', $pdf['content']), $document->final_file_hash);

        $finalized = DocumentEvent::where('document_id', $document->getKey())
            ->where('type', DocumentEvent::TYPE_FINALIZED)
            ->sole();
        $this->assertTrue($finalized->data['sealed_with_icp']);
    }

    public function test_signer_logged_into_another_workspace_signs_inside_the_document_workspace(): void
    {
        [$document, $first, $second] = $this->sentDocumentWithTwoSigners();
        $outsider = User::factory()->create();
        $otherTenant = Tenant::create(['name' => 'Outra', 'slug' => 'outra-'.Str::random(4), 'status' => 'active']);
        $otherTenant->users()->attach($outsider, ['role' => 'owner']);

        // A sessão do visitante põe o workspace dele no contexto; a rota pública troca para o do documento.
        $this->actingAs($outsider)->get(route('public-sign.show', $first->token))->assertOk();
        $this->assertSame($this->tenant->getKey(), app(CurrentTenant::class)->id());

        $this->actingAs($outsider);
        $this->sign($first);
        $this->sign($second);
        $this->assertSame(Document::STATUS_COMPLETED, $document->fresh()->status);

        // Na verificação pública o mesmo visitante vê os signatários do documento, não os do workspace dele.
        $this->actingAs($outsider)
            ->get(route('verify.show', substr($document->getKey(), -12)))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('signers', 2));
    }

    public function test_without_certificate_password_the_document_completes_unsealed(): void
    {
        [$document, $first, $second] = $this->sentDocumentWithTwoSigners();

        $this->sign($first);
        $this->sign($second);

        $document->refresh();
        $this->assertSame(Document::STATUS_COMPLETED, $document->status);
        $this->assertFalse($document->isSealed());
        $this->assertFalse($this->finalPdf($document)['signed']);
    }

    public function test_broken_certificate_does_not_block_the_last_signer(): void
    {
        $this->configureSeal('MODUHUB TESTE LTDA:12345678000190');
        config(['signatures.seal.certificate_password' => 'senha-errada']);
        [$document, $first, $second] = $this->sentDocumentWithTwoSigners();

        $this->sign($first);
        $this->sign($second);

        $document->refresh();
        $this->assertSame(Document::STATUS_COMPLETED, $document->status);
        $this->assertFalse($document->isSealed());
        $this->assertFalse($this->finalPdf($document)['signed']);
        $this->assertSame(DocumentSigner::STATUS_SIGNED, $second->fresh()->status);
    }

    public function test_everyone_involved_gets_the_signed_copy_once_the_last_person_signs(): void
    {
        $this->configureSeal('MODUHUB TESTE LTDA:12345678000190');
        [$document, $first, $second] = $this->sentDocumentWithTwoSigners();

        $this->sign($first);
        Mail::assertNotQueued(DocumentCompletedMail::class);

        $this->sign($second);
        $document->refresh();

        $signedPdf = Attachment::fromStorageDisk('documents', $document->final_file_path)
            ->as('contrato-assinado.pdf')
            ->withMime('application/pdf');

        $expected = ['ana@example.com', 'bruno@example.com', strtolower($this->owner->email)];
        foreach ($expected as $email) {
            Mail::assertQueued(DocumentCompletedMail::class, fn (DocumentCompletedMail $mail) => $mail->hasTo($email)
                && count($mail->attachments()) === 1
                && $mail->attachments()[0]->isEquivalent($signedPdf));
        }
        Mail::assertQueued(DocumentCompletedMail::class, 3);

        $event = DocumentEvent::where('document_id', $document->getKey())
            ->where('type', DocumentEvent::TYPE_COPIES_SENT)
            ->sole();
        $this->assertEqualsCanonicalizing($expected, $event->data['recipients']);
    }

    public function test_creator_who_also_signs_gets_a_single_copy(): void
    {
        $document = app(DocumentSubmissionServiceContract::class)->submit(
            $this->tenant,
            $this->owner,
            UploadedFile::fake()->createWithContent('contrato.pdf', $this->pdf()),
            ['name' => 'Contrato'],
            [['name' => $this->owner->name, 'email' => strtoupper($this->owner->email)]],
            true,
        );

        $this->sign($document->signers()->withoutTenantScope()->sole());

        Mail::assertQueued(DocumentCompletedMail::class, 1);
    }

    public function test_signers_can_no_longer_be_set_to_sign_with_their_own_certificate(): void
    {
        $document = Document::forceCreate([
            'tenant_id' => $this->tenant->getKey(),
            'name' => 'Rascunho',
            'status' => Document::STATUS_DRAFT,
            'file_path' => 'x/'.Str::ulid().'.pdf',
            'file_hash' => str_repeat('a', 64),
            'file_size' => 10,
            'page_count' => 1,
        ]);

        $this->actingAs($this->owner)
            ->withSession(['current_tenant_id' => $this->tenant->getKey()])
            ->post(route('signatures.signers.store', $document), [
                'name' => 'Ana',
                'email' => 'ana@example.com',
                'auth_method' => 'icp',
            ])
            ->assertSessionHasErrors('auth_method');
    }

    private function configureSeal(string $commonName): void
    {
        $this->certificatePath = tempnam(sys_get_temp_dir(), 'mdhb_seal_');
        // Cadeia anexa como num e-CNPJ real. Os certificados de teste são mais curtos que os da
        // ICP-Brasil, então são 5 ACs para o PKCS#7 passar do espaço padrão do TCPDF como o real passa.
        file_put_contents($this->certificatePath, $this->makePkcs12($commonName, chainLength: 5));

        config([
            'signatures.seal.certificate_path' => $this->certificatePath,
            'signatures.seal.certificate_password' => self::PKCS12_PASSWORD,
        ]);
    }

    /** @return array{0: Document, 1: DocumentSigner, 2: DocumentSigner} */
    private function sentDocumentWithTwoSigners(): array
    {
        $document = app(DocumentSubmissionServiceContract::class)->submit(
            $this->tenant,
            $this->owner,
            UploadedFile::fake()->createWithContent('contrato.pdf', $this->pdf()),
            ['name' => 'Contrato'],
            [
                ['name' => 'Ana', 'email' => 'ana@example.com'],
                ['name' => 'Bruno', 'email' => 'bruno@example.com'],
            ],
            true,
        );

        $signers = $document->signers()->withoutTenantScope()->orderBy('order')->get();

        return [$document, $signers[0], $signers[1]];
    }

    private function sign(DocumentSigner $signer): void
    {
        $signer->forceFill([
            'verification_code' => Hash::make('123456'),
            'verification_sent_at' => now(),
            'verification_attempts' => 0,
        ])->save();

        $this->post(route('public-sign.sign', $signer->token), ['code' => '123456', 'accepted' => true])
            ->assertRedirect(route('public-sign.thanks', $signer->token));
    }

    /** @return array{content: string, signed: bool} */
    private function finalPdf(Document $document): array
    {
        $content = Storage::disk('documents')->get($document->final_file_path);

        return [
            'content' => $content,
            'signed' => str_contains($content, '/adbe.pkcs7.detached') && str_contains($content, '/ByteRange'),
        ];
    }

    /**
     * O TCPDF corta em silêncio um PKCS#7 maior que o espaço reservado em /Contents, e o Adobe
     * acusa assinatura inválida. Confere que o tamanho declarado no cabeçalho DER coube inteiro.
     */
    private function assertPkcs7FitsInPdf(string $pdf): void
    {
        preg_match('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+\d+\s*\]/', $pdf, $m);
        $hex = trim(substr($pdf, (int) $m[2], (int) $m[3] - (int) $m[2]), '<>');

        $header = hex2bin(substr($hex, 0, 8));
        $this->assertSame(0x30, ord($header[0]), 'O /Contents não começa com uma SEQUENCE DER.');
        $lengthBytes = ord($header[1]) & 0x7F;
        $length = 0;
        for ($i = 0; $i < $lengthBytes; $i++) {
            $length = ($length << 8) | ord($header[2 + $i]);
        }

        $this->assertGreaterThan(5871, $length, 'A cadeia de teste deveria gerar um PKCS#7 maior que o padrão do TCPDF.');
        $this->assertLessThanOrEqual(strlen($hex), (2 + $lengthBytes + $length) * 2, 'PKCS#7 truncado no PDF.');
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
