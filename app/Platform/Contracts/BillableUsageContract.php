<?php

namespace App\Platform\Contracts;

use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Platform\Usage\BillableLine;
use Carbon\CarbonInterface;

/**
 * O que um módulo cobra além do plano num ciclo fechado. Registre a implementação com a tag
 * `billable-usage` no service provider do módulo.
 */
interface BillableUsageContract
{
    /**
     * Vazio quando não houve excedente ou o produto não está no carrinho. As condições do produto
     * vêm de `$subscription->termsFor(<módulo>)` (carregue `plans.modules`).
     *
     * @return list<BillableLine>
     */
    public function overage(Tenant $tenant, TenantSubscription $subscription, CarbonInterface $start, CarbonInterface $end): array;
}
