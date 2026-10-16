<?php

namespace App\Platform\Contracts;

use App\Models\TenantInvitation;
use App\Models\User;

interface InvitationServiceContract
{
    public function accept(TenantInvitation $invitation, User $user): void;
}
