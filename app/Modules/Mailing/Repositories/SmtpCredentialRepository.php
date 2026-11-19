<?php

namespace App\Modules\Mailing\Repositories;

use App\Models\Tenant;
use App\Modules\Mailing\Contracts\SmtpCredentialRepositoryContract;
use App\Modules\Mailing\Models\SmtpCredential;
use Illuminate\Support\Collection;

class SmtpCredentialRepository implements SmtpCredentialRepositoryContract
{
    public function forTenant(Tenant $tenant): Collection
    {
        return SmtpCredential::query()
            ->withoutTenantScope()
            ->where('tenant_id', $tenant->getKey())
            ->with('creator:id,name')
            ->latest()
            ->get();
    }

    public function countForTenant(Tenant $tenant): int
    {
        return SmtpCredential::query()
            ->withoutTenantScope()
            ->where('tenant_id', $tenant->getKey())
            ->count();
    }

    public function findByUsername(string $username): ?SmtpCredential
    {
        return SmtpCredential::query()
            ->withoutTenantScope()
            ->with('tenant')
            ->where('username', $username)
            ->first();
    }

    public function markUsed(SmtpCredential $credential): void
    {
        // Sem `save()` no modelo: não precisa de tenant no contexto nem dispara eventos à toa.
        SmtpCredential::query()
            ->withoutTenantScope()
            ->whereKey($credential->getKey())
            ->update(['last_used_at' => now()]);
    }

    public function create(array $attributes): SmtpCredential
    {
        return SmtpCredential::query()->create($attributes);
    }

    public function delete(SmtpCredential $credential): void
    {
        $credential->delete();
    }
}
