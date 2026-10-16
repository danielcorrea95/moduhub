<?php

namespace App\Platform\Contracts;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

interface TenantRepositoryContract
{
    public function workspacesForUser(User $user): Collection;

    public function activeModuleKeys(Tenant $tenant): array;
}
