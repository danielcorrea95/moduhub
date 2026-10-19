<?php

namespace App\Platform\Repositories;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantUser;
use App\Models\User;
use App\Platform\Contracts\MemberRepositoryContract;
use Illuminate\Support\Collection;

class MemberRepository implements MemberRepositoryContract
{
    public function listMembers(Tenant $tenant): Collection
    {
        return $tenant->users()
            ->orderBy('users.name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->pivot->role,
                'joined_at' => $u->pivot->joined_at,
            ]);
    }

    public function pendingInvitations(Tenant $tenant): Collection
    {
        return $tenant->invitations()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (TenantInvitation $i) => [
                'id' => $i->id,
                'email' => $i->email,
                'role' => $i->role,
                'expires_at' => $i->expires_at,
                'invite_url' => url('/invitations/' . $i->token),
            ]);
    }

    public function hasMemberWithEmail(Tenant $tenant, string $email): bool
    {
        return $tenant->users()->where('users.email', $email)->exists();
    }

    public function managers(Tenant $tenant): Collection
    {
        return $tenant->users()
            ->wherePivotIn('role', [TenantUser::ROLE_OWNER, TenantUser::ROLE_ADMIN])
            ->orderBy('users.name')
            ->get();
    }
}
