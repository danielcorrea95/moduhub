<?php

namespace App\Modules\Signatures\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Signatures\Contracts\DocumentServiceContract;
use App\Modules\Signatures\Mail\DocumentCompletedMail;
use App\Modules\Signatures\Mail\DocumentReadyMail;
use App\Modules\Signatures\Models\Document;
use App\Modules\Signatures\Models\DocumentEvent;
use App\Modules\Signatures\Models\DocumentSigner;
use App\Platform\Contracts\WebhookDispatcherContract;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DocumentService implements DocumentServiceContract
{
    public function __construct(
        protected PdfInspector $inspector,
        protected PdfComposer $composer,
        protected WebhookDispatcherContract $webhooks,
    ) {}

    public function createFromUpload(
        Tenant $tenant,
        ?User $user,
        UploadedFile $pdf,
        array $attrs,
    ): Document {
        return DB::transaction(function () use ($tenant, $user, $pdf, $attrs) {
            // O upload já está num arquivo local: mede antes de mandar para o disco (que pode ser o S3).
            $localPath = $pdf->getRealPath();
            $pageCount = $this->inspector->pageCount($localPath);
            $hash = hash_file('sha256', $localPath);
            $size = filesize($localPath);

            $relativePath = $tenant->getKey() . '/' . now()->format('Y/m') . '/' . uniqid('src_', true) . '.pdf';
            (new LocalScratch())->upload($localPath, Storage::disk('documents'), $relativePath);

            $document = Document::create([
                'tenant_id' => $tenant->getKey(),
                'creator_id' => $user?->getKey(),
                'name' => $attrs['name'] ?? pathinfo($pdf->getClientOriginalName(), PATHINFO_FILENAME),
                'status' => Document::STATUS_DRAFT,
                'file_disk' => 'documents',
                'file_path' => $relativePath,
                'file_hash' => $hash,
                'file_size' => $size,
                'page_count' => $pageCount,
                'sign_order' => $attrs['sign_order'] ?? Document::SIGN_ORDER_PARALLEL,
                'message' => $attrs['message'] ?? null,
                'external_id' => $attrs['external_id'] ?? null,
                'metadata' => $attrs['metadata'] ?? null,
                'expires_at' => $attrs['expires_at'] ?? null,
            ]);

            $this->recordEvent($document, DocumentEvent::TYPE_CREATED, $user, [
                'original_filename' => $pdf->getClientOriginalName(),
                'size_bytes' => $size,
                'page_count' => $pageCount,
                'sha256' => $hash,
            ]);

            return $document;
        });
    }

    public function send(Document $document, ?User $actor, ?Request $request = null): Document
    {
        if (! $document->isDraft()) {
            throw ValidationException::withMessages([
                'document' => 'Apenas documentos em rascunho podem ser enviados.',
            ]);
        }

        if ($document->signers()->count() === 0) {
            throw ValidationException::withMessages([
                'signers' => 'Adicione pelo menos um signatário antes de enviar.',
            ]);
        }

        $refreshed = DB::transaction(function () use ($document, $actor, $request) {
            $workingPath = $this->composer->buildWorkingPdf($document);

            $document->update([
                'status' => Document::STATUS_SENT,
                'final_file_path' => $workingPath,
                'sent_at' => now(),
            ]);

            $this->recordEvent(
                $document,
                DocumentEvent::TYPE_SENT,
                $actor,
                [
                    'signers_count' => $document->signers()->count(),
                    'working_path' => $workingPath,
                ],
                null,
                $request,
            );

            return $document->refresh();
        });

        $this->notifyInitialSigners($refreshed, $actor);

        if ($refreshed->tenant !== null) {
            $this->webhooks->fire($refreshed->tenant, 'document.sent', [
                'document_id' => $refreshed->getKey(),
                'external_id' => $refreshed->external_id,
                'sent_at' => $refreshed->sent_at?->toIso8601String(),
                'signers_count' => $refreshed->signers()->count(),
            ]);
        }

        return $refreshed;
    }

    protected function notifyInitialSigners(Document $document, ?User $sender): void
    {
        $signers = $document->signers()->orderBy('order')->get();

        $targets = $document->sign_order === Document::SIGN_ORDER_SEQUENTIAL
            ? $signers->take(1)
            : $signers;

        foreach ($targets as $signer) {
            $this->notifySigner($signer, $document, $sender);
        }
    }

    public function notifySigner(DocumentSigner $signer, Document $document, ?User $sender = null): void
    {
        try {
            Mail::to($signer->email)->send(
                new DocumentReadyMail($signer, $document, $document->tenant, $sender),
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to send document-ready email', [
                'signer_id' => $signer->getKey(),
                'document_id' => $document->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manda a cópia assinada para cada signatário e para quem criou o documento.
     * Um e-mail com problema não impede os outros nem desfaz a conclusão.
     */
    public function sendSignedCopies(Document $document): void
    {
        $recipients = [];
        foreach ($document->signers()->withoutTenantScope()->orderBy('order')->get() as $signer) {
            $recipients[strtolower($signer->email)] = $signer->name;
        }

        $creator = $document->creator;
        if ($creator !== null && ! isset($recipients[strtolower($creator->email)])) {
            $recipients[strtolower($creator->email)] = $creator->name;
        }

        $queued = [];
        foreach ($recipients as $email => $name) {
            try {
                Mail::to($email)->queue(new DocumentCompletedMail($document, $name, $email, $document->tenant));
                $queued[] = $email;
            } catch (\Throwable $e) {
                Log::warning('Failed to queue signed copy email', [
                    'document_id' => $document->getKey(),
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($queued !== []) {
            $this->recordEvent($document, DocumentEvent::TYPE_COPIES_SENT, null, ['recipients' => $queued]);
        }
    }

    public function regenerateWorkingPdf(Document $document): string
    {
        $workingPath = $this->composer->buildWorkingPdf($document);
        $document->forceFill(['final_file_path' => $workingPath])->save();

        return $workingPath;
    }

    public function delete(Document $document): void
    {
        if (! $document->isDraft()) {
            throw ValidationException::withMessages([
                'document' => 'Apenas documentos em rascunho podem ser excluídos.',
            ]);
        }

        DB::transaction(function () use ($document) {
            Storage::disk($document->file_disk)->delete($document->file_path);
            if ($document->final_file_path !== null) {
                Storage::disk($document->file_disk)->delete($document->final_file_path);
            }
            $document->delete();
        });
    }

    public function recordEvent(
        Document $document,
        string $type,
        ?User $user = null,
        array $data = [],
        ?string $signerId = null,
        ?Request $request = null,
    ): DocumentEvent {
        return DocumentEvent::create([
            'tenant_id' => $document->tenant_id,
            'document_id' => $document->getKey(),
            'signer_id' => $signerId,
            'user_id' => $user?->getKey(),
            'type' => $type,
            'data' => $data ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
