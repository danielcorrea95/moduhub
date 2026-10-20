<?php

namespace App\Modules\Mailing\Repositories;

use App\Models\Tenant;
use App\Modules\Mailing\Contracts\SendingDomainRepositoryContract;
use App\Modules\Mailing\Models\SendingDomain;
use Illuminate\Support\LazyCollection;

class SendingDomainRepository implements SendingDomainRepositoryContract
{
    public function forTenant(Tenant $tenant): ?SendingDomain
    {
        return SendingDomain::query()
            ->withoutTenantScope()
            ->where('tenant_id', $tenant->getKey())
            ->first();
    }

    public function create(array $attributes): SendingDomain
    {
        return SendingDomain::query()->create($attributes);
    }

    public function save(SendingDomain $domain): SendingDomain
    {
        $domain->save();

        return $domain;
    }

    public function delete(SendingDomain $domain): void
    {
        $domain->delete();
    }

    public function allForMonitoring(): LazyCollection
    {
        return SendingDomain::query()
            ->withoutTenantScope()
            ->with('tenant')
            ->lazyById();
    }
}
