<?php

namespace App\Modules\Signatures\Policies;

use App\Models\User;
use App\Modules\Signatures\Models\Document;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Document $document): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Document $document): bool
    {
        return $document->isDraft();
    }

    public function delete(User $user, Document $document): bool
    {
        return $document->isDraft();
    }

    public function manageSigners(User $user, Document $document): bool
    {
        return $document->isDraft();
    }

    public function send(User $user, Document $document): bool
    {
        return $document->isDraft();
    }

    /** Reenviar o link a um signatário só faz sentido enquanto o documento aguarda assinaturas. */
    public function remindSigners(User $user, Document $document): bool
    {
        return ! $document->isDraft() && ! $document->isFinalized();
    }
}
