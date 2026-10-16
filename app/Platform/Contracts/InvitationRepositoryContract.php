<?php

namespace App\Platform\Contracts;

use App\Models\TenantInvitation;

interface InvitationRepositoryContract
{
    public function findActiveByToken(string $token): ?TenantInvitation;

    public function findByToken(string $token): ?TenantInvitation;
}
