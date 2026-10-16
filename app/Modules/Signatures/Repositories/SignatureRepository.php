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

    public function remainingElectronicSignersFinished(Document $document, string $excludeSignerId): bool
    {
        $pending = DocumentSigner::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('document_id', $document->getKey())
            ->where('id', '!=', $excludeSignerId)
            ->where('auth_method', '!=', DocumentSigner::AUTH_ICP)
            ->whereNotIn('status', [
                DocumentSigner::STATUS_SIGNED,
                DocumentSigner::STATUS_REJECTED,
            ])
            ->count();

        return $pending === 0;
    }

    public function icpSignerCount(Document $document): int
    {
        return DocumentSigner::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('document_id', $document->getKey())
            ->where('auth_method', DocumentSigner::AUTH_ICP)
            ->count();
    }

    public function maxElectronicSignerOrder(Document $document): ?int
    {
        return DocumentSigner::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('document_id', $document->getKey())
            ->where('auth_method', '!=', DocumentSigner::AUTH_ICP)
            ->max('order');
    }

    public function minIcpSignerOrder(Document $document): ?int
    {
        return DocumentSigner::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('document_id', $document->getKey())
            ->where('auth_method', DocumentSigner::AUTH_ICP)
            ->min('order');
    }
}
