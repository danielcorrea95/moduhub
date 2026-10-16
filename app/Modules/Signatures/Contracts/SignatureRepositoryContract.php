<?php

namespace App\Modules\Signatures\Contracts;

use App\Modules\Signatures\Models\Document;
use App\Modules\Signatures\Models\DocumentSigner;

interface SignatureRepositoryContract
{
    public function findSignerByToken(string $token): ?DocumentSigner;

    public function pendingSignersBefore(Document $document, int $order): int;

    public function allSignersSigned(Document $document): bool;

    public function anySignerRejected(Document $document): bool;

    public function remainingElectronicSignersFinished(Document $document, string $excludeSignerId): bool;

    public function icpSignerCount(Document $document): int;

    public function maxElectronicSignerOrder(Document $document): ?int;

    public function minIcpSignerOrder(Document $document): ?int;
}
