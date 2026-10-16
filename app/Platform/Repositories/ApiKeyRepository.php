<?php

namespace App\Platform\Repositories;

use App\Models\ApiKey;
use App\Platform\Contracts\ApiKeyRepositoryContract;
use Illuminate\Support\Collection;

class ApiKeyRepository implements ApiKeyRepositoryContract
{
    public function listForCurrentTenant(): Collection
    {
        return ApiKey::query()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ApiKey $k) => [
                'id' => $k->getKey(),
                'name' => $k->name,
                'prefix' => $k->prefix,
                'last_four' => $k->last_four,
                'environment' => $k->environment,
                'scopes' => $k->scopes,
                'last_used_at' => $k->last_used_at,
                'expires_at' => $k->expires_at,
                'revoked_at' => $k->revoked_at,
                'created_at' => $k->created_at,
                'active' => $k->isActive(),
            ]);
    }
}
