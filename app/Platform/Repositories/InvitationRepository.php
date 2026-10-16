<?php

namespace App\Platform\Repositories;

use App\Models\TenantInvitation;
use App\Platform\Contracts\InvitationRepositoryContract;

class InvitationRepository implements InvitationRepositoryContract
{
    public function findActiveByToken(string $token): ?TenantInvitation
    {
        return TenantInvitation::with('tenant')
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function findByToken(string $token): ?TenantInvitation
    {
        return TenantInvitation::with('tenant')->where('token', $token)->first();
    }
}
