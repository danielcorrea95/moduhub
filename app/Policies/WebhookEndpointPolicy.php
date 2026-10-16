<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Tenancy\CurrentTenant;

class WebhookEndpointPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->userManagesCurrentTenant($user);
    }

    public function view(User $user, WebhookEndpoint $endpoint): bool
    {
        return $this->userManagesCurrentTenant($user);
    }

    public function create(User $user): bool
    {
        return $this->userManagesCurrentTenant($user);
    }

    public function update(User $user, WebhookEndpoint $endpoint): bool
    {
        return $this->userManagesCurrentTenant($user);
    }

    public function delete(User $user, WebhookEndpoint $endpoint): bool
    {
        return $this->userManagesCurrentTenant($user);
    }

    protected function userManagesCurrentTenant(User $user): bool
    {
        $tenant = app(CurrentTenant::class)->get();
        return $tenant !== null && $user->can('manageApi', $tenant);
    }
}
