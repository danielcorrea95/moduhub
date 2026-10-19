<?php

namespace App\Modules\Signatures\Services;

use App\Modules\Signatures\Contracts\DocumentServiceContract;
use App\Modules\Signatures\Contracts\SignatureRepositoryContract;
use App\Modules\Signatures\Contracts\SignatureServiceContract;
use App\Modules\Signatures\Mail\SignatureVerificationCodeMail;
use App\Modules\Signatures\Models\Document;
use App\Modules\Signatures\Models\DocumentEvent;
use App\Modules\Signatures\Models\DocumentSigner;
use App\Platform\Contracts\WebhookDispatcherContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SignatureService implements SignatureServiceContract
{
    protected const CODE_TTL_MINUTES = 15;
    protected const CODE_MAX_ATTEMPTS = 5;

    public function __construct(
        protected SignatureRepositoryContract $repo,
        protected DocumentServiceContract $documents,
        protected WebhookDispatcherContract $webhooks,
        protected PlatformSealService $seal,
    ) {}

    public function markViewed(DocumentSigner $signer, Request $request): void
    {
        $this->assertDocumentReceivable($signer);

        if ($signer->viewed_at !== null) {
            return;
        }

        $signer->forceFill([
            'status' => $signer->status === DocumentSigner::STATUS_PENDING
                ? DocumentSigner::STATUS_VIEWED
                : $signer->status,
            'viewed_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ])->save();

        $this->documents->recordEvent(
            $signer->document,
            DocumentEvent::TYPE_VIEWED,
            null,
            ['signer' => $this->signerSnapshot($signer)],
            $signer->getKey(),
            $request,
        );

        $this->fire($signer->document, 'document.viewed', [
            'document_id' => $signer->document->getKey(),
            'signer_id' => $signer->getKey(),
            'signer_email' => $signer->email,
        ]);
    }

    public function sendVerificationCode(DocumentSigner $signer, Request $request): void
    {
        $this->assertDocumentReceivable($signer);
        $this->assertSignerActionable($signer);
        $this->assertTurnIfSequential($signer);

        $code = (string) random_int(100000, 999999);

        $signer->forceFill([
            'verification_code' => Hash::make($code),
            'verification_sent_at' => now(),
            'verification_attempts' => 0,
        ])->save();

        $tenant = $signer->document->tenant;
        Mail::to($signer->email)->send(new SignatureVerificationCodeMail(
            signer: $signer,
            code: $code,
            documentName: $signer->document->name ?? 'Documento',
            tenant: $tenant,
        ));

        $this->documents->recordEvent(
            $signer->document,
            DocumentEvent::TYPE_CODE_SENT,
            null,
            ['signer_id' => $signer->getKey(), 'channel' => $signer->auth_method],
            $signer->getKey(),
            $request,
        );
    }

    public function sign(DocumentSigner $signer, string $code, Request $request, ?string $signatureImage = null): void
    {
        $this->assertDocumentReceivable($signer);
        $this->assertSignerActionable($signer);
        $this->assertTurnIfSequential($signer);

        if ($signer->verification_code === null || $signer->verification_sent_at === null) {
            throw ValidationException::withMessages([
                'code' => 'Solicite um novo código antes de assinar.',
            ]);
        }

        if ($signer->verification_sent_at->addMinutes(self::CODE_TTL_MINUTES)->isPast()) {
            throw ValidationException::withMessages([
                'code' => 'Código expirado. Solicite um novo.',
            ]);
        }

        if ($signer->verification_attempts >= self::CODE_MAX_ATTEMPTS) {
            throw ValidationException::withMessages([
                'code' => 'Limite de tentativas atingido. Solicite um novo código.',
            ]);
        }

        if (! Hash::check($code, $signer->verification_code)) {
            $signer->increment('verification_attempts');
            throw ValidationException::withMessages([
                'code' => 'Código inválido.',
            ]);
        }

        $imagePath = $this->persistSignatureImage($signer, $signatureImage);

        DB::transaction(function () use ($signer, $request, $imagePath) {
            $signer->forceFill([
                'status' => DocumentSigner::STATUS_SIGNED,
                'signature_type' => DocumentSigner::TYPE_ELECTRONIC,
                'signed_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'verification_code' => null,
                'signature_image_path' => $imagePath ?? $signer->signature_image_path,
            ])->save();

            $this->documents->recordEvent(
                $signer->document,
                DocumentEvent::TYPE_SIGNED,
                null,
                [
                    'signer' => $this->signerSnapshot($signer),
                    'signature_type' => DocumentSigner::TYPE_ELECTRONIC,
                ],
                $signer->getKey(),
                $request,
            );

            $this->fire($signer->document, 'document.signed', [
                'document_id' => $signer->document->getKey(),
                'signer_id' => $signer->getKey(),
                'signer_email' => $signer->email,
                'signed_at' => $signer->signed_at?->toIso8601String(),
            ]);

            // Na última assinatura o PDF é montado uma vez só, já com o selo, em completeDocument.
            if ($this->repo->allSignersSigned($signer->document)) {
                $this->completeDocument($signer->document, $request);
            } else {
                $this->documents->regenerateWorkingPdf($signer->document->fresh());
                $signer->document->update(['status' => Document::STATUS_PARTIALLY_SIGNED]);
                $this->notifyNextIfSequential($signer->document->fresh());
            }
        });
    }

    protected function notifyNextIfSequential(Document $document): void
    {
        if ($document->sign_order !== Document::SIGN_ORDER_SEQUENTIAL) {
            return;
        }

        $next = $document->signers()
            ->whereNotIn('status', [DocumentSigner::STATUS_SIGNED, DocumentSigner::STATUS_REJECTED])
            ->orderBy('order')
            ->first();

        if ($next === null) {
            return;
        }

        $this->documents->notifySigner($next, $document);
    }

    public function reject(DocumentSigner $signer, string $reason, Request $request): void
    {
        $this->assertDocumentReceivable($signer);
        $this->assertSignerActionable($signer);

        DB::transaction(function () use ($signer, $reason, $request) {
            $signer->forceFill([
                'status' => DocumentSigner::STATUS_REJECTED,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'verification_code' => null,
            ])->save();

            $signer->document->update([
                'status' => Document::STATUS_REJECTED,
                'cancelled_at' => now(),
            ]);

            $this->documents->recordEvent(
                $signer->document,
                DocumentEvent::TYPE_REJECTED,
                null,
                [
                    'signer' => $this->signerSnapshot($signer),
                    'reason' => $reason,
                ],
                $signer->getKey(),
                $request,
            );

            $this->documents->regenerateWorkingPdf($signer->document->fresh());

            $this->fire($signer->document, 'document.rejected', [
                'document_id' => $signer->document->getKey(),
                'signer_id' => $signer->getKey(),
                'signer_email' => $signer->email,
                'reason' => $reason,
            ]);
        });
    }

    protected function completeDocument(Document $document, Request $request): void
    {
        $document->update([
            'status' => Document::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $fresh = $document->fresh();
        $sealed = $this->seal->finalize($fresh);

        $fresh->forceFill([
            'final_file_hash' => $this->hashStoredFile($fresh->file_disk, $fresh->final_file_path),
        ])->save();

        $this->documents->recordEvent(
            $fresh,
            DocumentEvent::TYPE_FINALIZED,
            null,
            array_filter([
                'sha256_final' => $fresh->final_file_hash,
                'sealed_with_icp' => $sealed,
                'seal_certificate' => $sealed ? [
                    'subject' => $fresh->seal_certificate_subject,
                    'issuer' => $fresh->seal_certificate_issuer,
                    'serial' => $fresh->seal_certificate_serial,
                ] : null,
            ], fn ($value) => $value !== null),
            null,
            $request,
        );

        $this->fire($fresh, 'document.completed', [
            'document_id' => $fresh->getKey(),
            'completed_at' => $fresh->completed_at?->toIso8601String(),
            'final_sha256' => $fresh->final_file_hash,
            'sealed_with_icp' => $sealed,
        ]);

        // Só depois do commit: se a transação da última assinatura falhar, ninguém recebe cópia.
        DB::afterCommit(fn () => $this->documents->sendSignedCopies($fresh->fresh()));
    }

    protected function assertDocumentReceivable(DocumentSigner $signer): void
    {
        $document = $signer->document;

        if ($document === null) {
            throw ValidationException::withMessages(['document' => 'Documento não encontrado.']);
        }

        if ($document->expires_at !== null && $document->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'document' => 'O prazo deste documento expirou.',
            ]);
        }

        $openStatuses = [
            Document::STATUS_SENT,
            Document::STATUS_PARTIALLY_SIGNED,
        ];
        if (! in_array($document->status, $openStatuses, true)) {
            throw ValidationException::withMessages([
                'document' => 'Este documento não está mais disponível para assinatura.',
            ]);
        }
    }

    protected function assertSignerActionable(DocumentSigner $signer): void
    {
        if ($signer->status === DocumentSigner::STATUS_SIGNED) {
            throw ValidationException::withMessages(['signer' => 'Você já assinou este documento.']);
        }
        if ($signer->status === DocumentSigner::STATUS_REJECTED) {
            throw ValidationException::withMessages(['signer' => 'Você já rejeitou este documento.']);
        }
    }

    protected function assertTurnIfSequential(DocumentSigner $signer): void
    {
        if ($signer->document->sign_order !== Document::SIGN_ORDER_SEQUENTIAL) {
            return;
        }

        $pendingBefore = $this->repo->pendingSignersBefore($signer->document, $signer->order);
        if ($pendingBefore > 0) {
            throw ValidationException::withMessages([
                'signer' => 'Aguardando signatários anteriores concluírem.',
            ]);
        }
    }

    protected function persistSignatureImage(DocumentSigner $signer, ?string $base64): ?string
    {
        if ($base64 === null || $base64 === '') {
            return null;
        }

        if (! str_starts_with($base64, 'data:image/png;base64,')) {
            return null;
        }

        $binary = base64_decode(substr($base64, strlen('data:image/png;base64,')), true);
        if ($binary === false || strlen($binary) < 128) {
            return null;
        }

        if (substr($binary, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return null;
        }

        $document = $signer->document;
        $disk = Storage::disk($document->file_disk);
        $folder = pathinfo($document->file_path, PATHINFO_DIRNAME);
        $path = $folder . '/signatures/' . $signer->getKey() . '_' . Str::random(8) . '.png';

        $disk->put($path, $binary);

        return $path;
    }

    /** SHA-256 lido em stream do disco, sem trazer o PDF inteiro para a memória. */
    protected function hashStoredFile(string $disk, ?string $path): ?string
    {
        $storage = Storage::disk($disk);
        if ($path === null || ! $storage->exists($path)) {
            return null;
        }

        $stream = $storage->readStream($path);
        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        return hash_final($context);
    }

    protected function signerSnapshot(DocumentSigner $signer): array
    {
        return [
            'id' => $signer->getKey(),
            'name' => $signer->name,
            'email' => $signer->email,
            'order' => $signer->order,
        ];
    }

    protected function fire(Document $document, string $event, array $data): void
    {
        if ($document->tenant === null) {
            return;
        }

        // Todo payload identifica o documento também pelo id do sistema do cliente.
        $this->webhooks->fire($document->tenant, $event, array_merge([
            'document_id' => $document->getKey(),
            'external_id' => $document->external_id,
        ], $data));
    }
}
