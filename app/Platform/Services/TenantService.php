<?php

namespace App\Platform\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Platform\Contracts\TenantServiceContract;
use App\Tenancy\TenantFactory;

class TenantService implements TenantServiceContract
{
    public function __construct(protected TenantFactory $factory) {}

    public function create(User $owner, array $attrs = []): Tenant
    {
        return $this->factory->createForOwner($owner, $attrs);
    }

    public function updateProfile(Tenant $tenant, array $attrs): Tenant
    {
        $tenant->update([
            'name' => $attrs['name'],
            'tax_id' => $attrs['tax_id'] ?? null,
            'branding' => array_merge($tenant->branding ?? [], $attrs['branding'] ?? []),
        ]);

        return $tenant->refresh();
    }
}
