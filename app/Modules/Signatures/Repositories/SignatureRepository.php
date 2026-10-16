<?php

namespace App\Modules\Signatures\Repositories;

use App\Modules\Signatures\Contracts\SignatureRepositoryContract;
use App\Modules\Signatures\Models\Document;
use App\Modules\Signatures\Models\DocumentSigner;
use App\Tenancy\TenantScope;

class SignatureRepository implements SignatureRepositoryContract
{
    public function findSignerByToken(string $token): ?DocumentSigner
    {
        return DocumentSigner::query()
            ->withoutGlobalScope(TenantScope::class)
            ->with([
                'document' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->with('tenant'),
            ])
            ->where('token', $token)
            ->first();
    }

    public function pendingSignersBefore(Document $document, int $order): int
    {
        return DocumentSigner::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('document_id', $document->getKey())
            ->where('order', '<', $order)
            ->whereNotIn('status', [
                DocumentSigner::STATUS_SIGNED,
                DocumentSigner::STATUS_REJECTED,
            ])
            ->count();
    }

    public function allSignersSigned(Document $document): bool
    {
        $total = DocumentSigner::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('document_id', $document->getKey())
            ->count();

        if ($total === 0) {
            return false;
        }

        $signed = DocumentSigner::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('document_id', $document->getKey())
            ->where('status', DocumentSigner::STATUS_SIGNED)
            ->count();

        return $signed === $total;
    }

    public function anySignerRejected(Document $document): bool
    {
        return DocumentSigner::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('document_id', $document->getKey())
            ->where('status', DocumentSigner::STATUS_REJECTED)
            ->exists();
    }
}
