<?php

namespace App\Platform\Services;

use App\Mail\TenantInvitationMail;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantUser;
use App\Models\User;
use App\Platform\Contracts\MemberRepositoryContract;
use App\Platform\Contracts\MemberServiceContract;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MemberService implements MemberServiceContract
{
    public function __construct(protected MemberRepositoryContract $members) {}

    public function invite(Tenant $tenant, User $actor, string $email, string $role): TenantInvitation
    {
        $email = strtolower($email);

        if ($this->members->hasMemberWithEmail($tenant, $email)) {
            throw ValidationException::withMessages([
                'email' => 'Esse e-mail já é membro deste workspace.',
            ]);
        }

        $invitation = TenantInvitation::create([
            'tenant_id' => $tenant->getKey(),
            'email' => $email,
            'role' => $role,
            'token' => Str::random(48),
            'invited_by' => $actor->getKey(),
            'expires_at' => now()->addDays(7),
        ]);

        $this->sendInvitationEmail($invitation, $tenant, $actor);

        return $invitation;
    }

    protected function sendInvitationEmail(TenantInvitation $invitation, Tenant $tenant, User $inviter): void
    {
        try {
            $acceptUrl = route('invitations.show', ['token' => $invitation->token]);

            Mail::to($invitation->email)->send(
                new TenantInvitationMail($invitation, $tenant, $inviter, $acceptUrl),
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to send tenant invitation email', [
                'invitation_id' => $invitation->getKey(),
                'tenant_id' => $tenant->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function revokeInvitation(TenantInvitation $invitation): void
    {
        $invitation->delete();
    }

    public function removeMember(Tenant $tenant, User $member): void
    {
        $role = $member->tenantRole($tenant);

        if ($role === TenantUser::ROLE_OWNER) {
            throw ValidationException::withMessages([
                'user' => 'O owner não pode ser removido.',
            ]);
        }

        $tenant->users()->detach($member->getKey());
    }

    public function updateRole(Tenant $tenant, User $member, string $role): void
    {
        if ($member->tenantRole($tenant) === TenantUser::ROLE_OWNER) {
            throw ValidationException::withMessages([
                'role' => 'Não é possível alterar o role do owner.',
            ]);
        }

        $tenant->users()->updateExistingPivot($member->getKey(), ['role' => $role]);
    }
}
