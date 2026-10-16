<?php

namespace App\Platform\Services;

use App\Models\TenantInvitation;
use App\Models\TenantUser;
use App\Models\User;
use App\Platform\Contracts\InvitationServiceContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvitationService implements InvitationServiceContract
{
    public function accept(TenantInvitation $invitation, User $user): void
    {
        if ($invitation->accepted_at !== null || $invitation->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'invitation' => 'Convite expirado ou já utilizado.',
            ]);
        }

        if (strcasecmp($invitation->email, $user->email) !== 0) {
            throw ValidationException::withMessages([
                'email' => 'Este convite é para outro e-mail.',
            ]);
        }

        DB::transaction(function () use ($invitation, $user) {
            $invitation->tenant->users()->syncWithoutDetaching([
                $user->getKey() => [
                    'role' => $invitation->role ?: TenantUser::ROLE_MEMBER,
                    'joined_at' => now(),
                ],
            ]);
            $invitation->forceFill(['accepted_at' => now()])->save();
        });
    }
}
