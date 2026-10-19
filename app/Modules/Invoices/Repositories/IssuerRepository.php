<?php

namespace App\Modules\Invoices\Repositories;

use App\Modules\Invoices\Contracts\IssuerRepositoryContract;
use App\Modules\Invoices\Models\Issuer;

class IssuerRepository implements IssuerRepositoryContract
{
    public function forCurrentTenant(): ?Issuer
    {
        return Issuer::query()->first();
    }

    public function forTenant(string $tenantId): ?Issuer
    {
        return Issuer::query()->withoutTenantScope()->where('tenant_id', $tenantId)->first();
    }

    public function lockForNumbering(Issuer $issuer): Issuer
    {
        // Pela chave do emitente já resolvido: o scope de tenant não acrescenta nada aqui.
        return Issuer::query()->withoutTenantScope()->whereKey($issuer->getKey())->lockForUpdate()->firstOrFail();
    }
}
