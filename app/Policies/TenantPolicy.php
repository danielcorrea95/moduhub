<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

class TenantPolicy
{
    public function view(User $user, Tenant $tenant): bool
    {
        return $user->belongsToTenant($tenant);
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $this->hasRole($user, $tenant, [TenantUser::ROLE_OWNER, TenantUser::ROLE_ADMIN]);
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $this->hasRole($user, $tenant, [TenantUser::ROLE_OWNER]);
    }

    public function manageMembers(User $user, Tenant $tenant): bool
    {
        return $this->hasRole($user, $tenant, [TenantUser::ROLE_OWNER, TenantUser::ROLE_ADMIN]);
    }

    public function manageBilling(User $user, Tenant $tenant): bool
    {
        return $this->hasRole($user, $tenant, [TenantUser::ROLE_OWNER]);
    }

    public function manageApi(User $user, Tenant $tenant): bool
    {
        return $this->hasRole($user, $tenant, [TenantUser::ROLE_OWNER, TenantUser::ROLE_ADMIN]);
    }

    protected function hasRole(User $user, Tenant $tenant, array $roles): bool
    {
        $role = $user->tenantRole($tenant);
        return $role !== null && in_array($role, $roles, true);
    }
}
