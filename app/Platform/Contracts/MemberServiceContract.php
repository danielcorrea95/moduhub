<?php

namespace App\Platform\Contracts;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;

interface MemberServiceContract
{
    public function invite(Tenant $tenant, User $actor, string $email, string $role): TenantInvitation;

    public function revokeInvitation(TenantInvitation $invitation): void;

    public function removeMember(Tenant $tenant, User $member): void;

    public function updateRole(Tenant $tenant, User $member, string $role): void;
}
