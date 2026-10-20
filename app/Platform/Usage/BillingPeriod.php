<?php

namespace App\Platform\Usage;

use App\Models\TenantSubscription;
use Carbon\CarbonImmutable;

/**
 * Ciclo de cobrança: o período atual da assinatura ou, sem ele, o mês do calendário.
 * Mesma regra da cota de NFS-e.
 */
final class BillingPeriod
{
    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} início e fim (exclusivo) */
    public static function for(?TenantSubscription $subscription): array
    {
        $start = $subscription?->current_period_start;
        $end = $subscription?->current_period_end;

        if ($start !== null && $end !== null && $end->isFuture()) {
            return [CarbonImmutable::parse($start), CarbonImmutable::parse($end)];
        }

        $now = CarbonImmutable::now();

        return [$now->startOfMonth(), $now->startOfMonth()->addMonth()];
    }
}
