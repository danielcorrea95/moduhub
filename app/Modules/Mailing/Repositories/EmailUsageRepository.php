<?php

namespace App\Modules\Mailing\Repositories;

use App\Models\Module;
use App\Models\Tenant;
use App\Models\TenantModuleUsage;
use App\Models\TenantSubscription;
use App\Modules\Mailing\Contracts\EmailUsageRepositoryContract;

class EmailUsageRepository implements EmailUsageRepositoryContract
{
    private ?int $moduleId = null;

    public function subscriptionFor(Tenant $tenant): ?TenantSubscription
    {
        return TenantSubscription::query()
            ->withoutTenantScope()
            ->where('tenant_id', $tenant->getKey())
            ->with('plan.modules')
            ->latest('id')
            ->first();
    }

    public function lockDay(Tenant $tenant, string $date): TenantModuleUsage
    {
        $keys = ['tenant_id' => $tenant->getKey(), 'module_id' => $this->moduleId(), 'period' => $date];

        TenantModuleUsage::query()->insertOrIgnore($keys + ['count' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return TenantModuleUsage::query()->where($keys)->lockForUpdate()->firstOrFail();
    }

    public function sumBetween(Tenant $tenant, string $from, string $to): int
    {
        return (int) TenantModuleUsage::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('module_id', $this->moduleId())
            ->whereBetween('period', [$from, $to])
            ->sum('count');
    }

    public function countOn(Tenant $tenant, string $date): int
    {
        return (int) TenantModuleUsage::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('module_id', $this->moduleId())
            ->where('period', $date)
            ->value('count');
    }

    public function add(TenantModuleUsage $day, int $messages): void
    {
        $day->count = max(0, $day->count + $messages);
        $day->save();
    }

    private function moduleId(): int
    {
        return $this->moduleId ??= Module::query()->where('key', Module::MAILING)->valueOrFail('id');
    }
}
