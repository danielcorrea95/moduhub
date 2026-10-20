<?php

namespace App\Platform\Services;

use App\Models\TenantInvitation;
use App\Models\TenantUser;
use App\Models\User;
use App\Platform\Contracts\InvitationRepositoryContract;
use App\Platform\Contracts\RegistrationServiceContract;
use App\Platform\Contracts\TenantServiceContract;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegistrationService implements RegistrationServiceContract
{
    public function __construct(
        protected TenantServiceContract $tenants,
        protected InvitationRepositoryContract $invitations,
    ) {}

    public function register(array $attrs): array
    {
        return DB::transaction(function () use ($attrs) {
            $user = User::create([
                'name' => $attrs['name'],
                'email' => $attrs['email'],
                'password' => Hash::make($attrs['password']),
            ]);

            event(new Registered($user));

            $invitation = $this->resolveInvitation($attrs['invitation_token'] ?? null, $user->email);

            if ($invitation !== null) {
                $invitation->tenant->users()->syncWithoutDetaching([
                    $user->getKey() => [
                        'role' => $invitation->role,
                        'joined_at' => now(),
                    ],
                ]);
                $invitation->forceFill(['accepted_at' => now()])->save();

                return ['user' => $user, 'tenant_id' => $invitation->tenant_id];
            }

            $tenant = $this->tenants->create($user, [
                'name' => ($attrs['workspace_name'] ?? null) ?: ($user->name . "'s workspace"),
                'plan_keys' => $attrs['plans'] ?? [],
            ]);

            return ['user' => $user, 'tenant_id' => $tenant->getKey()];
        });
    }

    protected function resolveInvitation(?string $token, string $email): ?TenantInvitation
    {
        if ($token === null || $token === '') {
            return null;
        }

        $invitation = $this->invitations->findActiveByToken($token);

        if ($invitation === null || strcasecmp($invitation->email, $email) !== 0) {
            return null;
        }

        return $invitation;
    }
}
