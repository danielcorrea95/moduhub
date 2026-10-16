<?php

namespace App\Modules\Signatures\Services;

use App\Models\User;
use App\Modules\Signatures\Contracts\DocumentServiceContract;
use App\Modules\Signatures\Contracts\SignatureRepositoryContract;
use App\Modules\Signatures\Contracts\SignerRepositoryContract;
use App\Modules\Signatures\Contracts\SignerServiceContract;
use App\Modules\Signatures\Models\Document;
use App\Modules\Signatures\Models\DocumentEvent;
use App\Modules\Signatures\Models\DocumentSigner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SignerService implements SignerServiceContract
{
    public function __construct(
        protected SignerRepositoryContract $signers,
        protected DocumentServiceContract $documents,
        protected SignatureRepositoryContract $signatures,
    ) {}

    public function isAwaitingSignature(DocumentSigner $signer): bool
    {
        $document = $signer->document;

        if ($document->isDraft() || $document->isFinalized()) {
            return false;
        }

        if (! in_array($signer->status, [DocumentSigner::STATUS_PENDING, DocumentSigner::STATUS_VIEWED], true)) {
            return false;
        }

        return $document->sign_order !== Document::SIGN_ORDER_SEQUENTIAL
            || $this->signatures->pendingSignersBefore($document, $signer->order) === 0;
    }

    public function remind(DocumentSigner $signer, ?User $actor): void
    {
        if (! $this->isAwaitingSignature($signer)) {
            throw ValidationException::withMessages([
                'signer' => 'Este signatário não está aguardando assinatura agora.',
            ]);
        }

        $document = $signer->document;
        $this->documents->notifySigner($signer, $document, $actor);

        $this->documents->recordEvent($document, DocumentEvent::TYPE_REMINDED, $actor, [
            'signer' => ['id' => $signer->getKey(), 'name' => $signer->name, 'email' => $signer->email],
        ], $signer->getKey());
    }

    public function add(Document $document, ?User $actor, array $attrs): DocumentSigner
    {
        $this->assertDraft($document);

        $email = strtolower($attrs['email']);
        if ($this->signers->emailExistsForDocument($document, $email)) {
            throw ValidationException::withMessages([
                'email' => 'Já existe um signatário com esse e-mail neste documento.',
            ]);
        }

        return DB::transaction(function () use ($document, $actor, $attrs, $email) {
            $signer = DocumentSigner::create([
                'tenant_id' => $document->tenant_id,
                'document_id' => $document->getKey(),
                'order' => $attrs['order'] ?? $this->signers->nextOrder($document),
                'name' => $attrs['name'],
                'role' => $this->trimmedOrNull($attrs['role'] ?? null),
                'email' => $email,
                'cpf' => $attrs['cpf'] ?? null,
                'cnpj' => $this->digitsOrNull($attrs['cnpj'] ?? null),
                'phone' => $attrs['phone'] ?? null,
                'auth_method' => $attrs['auth_method'] ?? DocumentSigner::AUTH_EMAIL,
                'status' => DocumentSigner::STATUS_PENDING,
                'token' => Str::random(48),
            ]);

            $this->documents->recordEvent($document, DocumentEvent::TYPE_UPDATED, $actor, [
                'change' => 'signer_added',
                'signer' => ['id' => $signer->getKey(), 'name' => $signer->name, 'email' => $signer->email],
            ], $signer->getKey());

            return $signer;
        });
    }

    public function update(DocumentSigner $signer, ?User $actor, array $attrs): DocumentSigner
    {
        $this->assertDraft($signer->document);

        if (isset($attrs['email'])) {
            $attrs['email'] = strtolower($attrs['email']);
            if ($this->signers->emailExistsForDocument($signer->document, $attrs['email'], $signer->getKey())) {
                throw ValidationException::withMessages([
                    'email' => 'Já existe um signatário com esse e-mail neste documento.',
                ]);
            }
        }

        if (array_key_exists('cnpj', $attrs)) {
            $attrs['cnpj'] = $this->digitsOrNull($attrs['cnpj']);
        }

        if (array_key_exists('role', $attrs)) {
            $attrs['role'] = $this->trimmedOrNull($attrs['role']);
        }

        return DB::transaction(function () use ($signer, $actor, $attrs) {
            $signer->update(array_intersect_key($attrs, array_flip([
                'name', 'role', 'email', 'cpf', 'cnpj', 'phone', 'auth_method',
            ])));

            $this->documents->recordEvent($signer->document, DocumentEvent::TYPE_UPDATED, $actor, [
                'change' => 'signer_updated',
                'signer_id' => $signer->getKey(),
            ], $signer->getKey());

            return $signer->refresh();
        });
    }

    public function remove(DocumentSigner $signer, ?User $actor): void
    {
        $this->assertDraft($signer->document);

        DB::transaction(function () use ($signer, $actor) {
            $document = $signer->document;
            $signerSnapshot = ['id' => $signer->getKey(), 'name' => $signer->name, 'email' => $signer->email];

            $signer->delete();

            $this->resequence($document);

            $this->documents->recordEvent($document, DocumentEvent::TYPE_UPDATED, $actor, [
                'change' => 'signer_removed',
                'signer' => $signerSnapshot,
            ]);
        });
    }

    public function reorder(Document $document, ?User $actor, array $signerIdsInOrder): void
    {
        $this->assertDraft($document);

        $existing = $this->signers->listByDocument($document);
        $existingIds = $existing->pluck('id')->all();

        $missing = array_diff($existingIds, $signerIdsInOrder);
        $extra = array_diff($signerIdsInOrder, $existingIds);
        if (count($missing) > 0 || count($extra) > 0) {
            throw ValidationException::withMessages([
                'signers' => 'Lista de signatários inconsistente com o documento.',
            ]);
        }

        DB::transaction(function () use ($document, $actor, $signerIdsInOrder) {
            foreach ($signerIdsInOrder as $index => $id) {
                DocumentSigner::query()
                    ->where('document_id', $document->getKey())
                    ->where('id', $id)
                    ->update(['order' => $index + 1]);
            }

            $this->documents->recordEvent($document, DocumentEvent::TYPE_UPDATED, $actor, [
                'change' => 'signers_reordered',
                'order' => $signerIdsInOrder,
            ]);
        });
    }

    /** CNPJ é guardado só com dígitos (a coluna tem 14); aceitamos com ou sem máscara na entrada. */
    protected function digitsOrNull(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        return $digits === '' ? null : $digits;
    }

    protected function trimmedOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function resequence(Document $document): void
    {
        $signers = $this->signers->listByDocument($document);
        foreach ($signers as $index => $signer) {
            if ($signer->order !== $index + 1) {
                $signer->update(['order' => $index + 1]);
            }
        }
    }

    protected function assertDraft(Document $document): void
    {
        if (! $document->isDraft()) {
            throw ValidationException::withMessages([
                'document' => 'Signatários só podem ser editados em documentos em rascunho.',
            ]);
        }
    }
}
