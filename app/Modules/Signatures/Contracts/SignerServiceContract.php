<?php

namespace App\Modules\Signatures\Contracts;

use App\Models\User;
use App\Modules\Signatures\Models\Document;
use App\Modules\Signatures\Models\DocumentSigner;

interface SignerServiceContract
{
    public function add(Document $document, ?User $actor, array $attrs): DocumentSigner;

    public function update(DocumentSigner $signer, ?User $actor, array $attrs): DocumentSigner;

    public function remove(DocumentSigner $signer, ?User $actor): void;

    /**
     * @param array<int, string> $signerIdsInOrder
     */
    public function reorder(Document $document, ?User $actor, array $signerIdsInOrder): void;

    /**
     * O signatário pode agir agora: documento em andamento, ele ainda não assinou nem recusou
     * e, na ordem sequencial, já é a vez dele.
     */
    public function isAwaitingSignature(DocumentSigner $signer): bool;

    /** Reenvia por e-mail o link de assinatura. */
    public function remind(DocumentSigner $signer, ?User $actor): void;
}
