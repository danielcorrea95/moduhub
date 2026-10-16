<?php

namespace App\Platform\Repositories;

use App\Models\Tenant;
use App\Models\User;
use App\Platform\Contracts\TenantRepositoryContract;
use Illuminate\Support\Collection;

class TenantRepository implements TenantRepositoryContract
{
    public function workspacesForUser(User $user): Collection
    {
        return $user->tenants()
            ->orderBy('tenants.name')
            ->get()
            ->map(fn (Tenant $t) => [
                'id' => $t->getKey(),
                'name' => $t->name,
                'slug' => $t->slug,
                'role' => $t->pivot->role,
            ]);
    }

    public function activeModuleKeys(Tenant $tenant): array
    {
        return $tenant->activeModules()->pluck('key')->all();
    }
}
