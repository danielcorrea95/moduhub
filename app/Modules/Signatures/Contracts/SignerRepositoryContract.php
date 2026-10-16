<?php

namespace App\Modules\Signatures\Contracts;

use App\Modules\Signatures\Models\Document;
use App\Modules\Signatures\Models\DocumentSigner;
use Illuminate\Support\Collection;

interface SignerRepositoryContract
{
    public function listByDocument(Document $document): Collection;

    public function nextOrder(Document $document): int;

    public function emailExistsForDocument(Document $document, string $email, ?string $exceptSignerId = null): bool;

    public function findInDocument(Document $document, string $signerId): ?DocumentSigner;
}
