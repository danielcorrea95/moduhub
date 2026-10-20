<?php

namespace App\Platform\Services;

use App\Models\Tenant;
use App\Platform\Contracts\UsageMeterContract;
use App\Platform\Usage\UsageMeter;
use Illuminate\Support\Facades\Cache;

/**
 * Junta os medidores de uso dos módulos para a barra lateral.
 */
class PlanUsageService
{
    /** Vai em toda navegação do painel; um minuto de atraso no número não faz diferença. */
    public const CACHE_SECONDS = 60;

    /** @param  iterable<UsageMeterContract>  $meters */
    public function __construct(
        private iterable $meters,
    ) {}

    /** @return list<array<string, mixed>> */
    public function forTenant(Tenant $tenant): array
    {
        return Cache::remember(self::cacheKey($tenant), self::CACHE_SECONDS, fn () => collect($this->meters)
            ->map(fn (UsageMeterContract $provider) => $provider->meter($tenant))
            ->filter()
            ->map(fn (UsageMeter $meter) => $meter->toArray())
            ->values()
            ->all());
    }

    public static function forget(Tenant $tenant): void
    {
        Cache::forget(self::cacheKey($tenant));
    }

    private static function cacheKey(Tenant $tenant): string
    {
        return "plan-usage:{$tenant->getKey()}";
    }
}
