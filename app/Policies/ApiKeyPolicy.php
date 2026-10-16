<?php

namespace App\Policies;

use App\Models\ApiKey;
use App\Models\User;
use App\Tenancy\CurrentTenant;

class ApiKeyPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->userManagesCurrentTenant($user);
    }

    public function create(User $user): bool
    {
        return $this->userManagesCurrentTenant($user);
    }

    public function delete(User $user, ApiKey $apiKey): bool
    {
        return $this->userManagesCurrentTenant($user);
    }

    protected function userManagesCurrentTenant(User $user): bool
    {
        $tenant = app(CurrentTenant::class)->get();
        return $tenant !== null && $user->can('manageApi', $tenant);
    }
}
