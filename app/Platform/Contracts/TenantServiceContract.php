<?php

namespace App\Platform\Contracts;

use App\Models\Tenant;
use App\Models\User;

interface TenantServiceContract
{
    public function create(User $owner, array $attrs = []): Tenant;

    public function updateProfile(Tenant $tenant, array $attrs): Tenant;
}
