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

    public function create(array $attributes): SmtpCredential
    {
        return SmtpCredential::query()->create($attributes);
    }

    public function delete(SmtpCredential $credential): void
    {
        $credential->delete();
    }
}
