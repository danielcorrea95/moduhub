<?php

namespace App\Modules\Signatures\Repositories;

use App\Modules\Signatures\Contracts\SignerRepositoryContract;
use App\Modules\Signatures\Models\Document;
use App\Modules\Signatures\Models\DocumentSigner;
use Illuminate\Support\Collection;

class SignerRepository implements SignerRepositoryContract
{
    public function listByDocument(Document $document): Collection
    {
        return DocumentSigner::query()
            ->where('document_id', $document->getKey())
            ->orderBy('order')
            ->get();
    }

    public function nextOrder(Document $document): int
    {
        return (int) DocumentSigner::query()
            ->where('document_id', $document->getKey())
            ->max('order') + 1;
    }

    public function emailExistsForDocument(Document $document, string $email, ?string $exceptSignerId = null): bool
    {
        return DocumentSigner::query()
            ->where('document_id', $document->getKey())
            ->where('email', $email)
            ->when($exceptSignerId, fn ($q) => $q->where('id', '!=', $exceptSignerId))
            ->exists();
    }

    public function findInDocument(Document $document, string $signerId): ?DocumentSigner
    {
        return DocumentSigner::query()
            ->where('document_id', $document->getKey())
            ->where('id', $signerId)
            ->first();
    }
}
