<?php

namespace App\Modules\Billing\Contracts;

use App\Models\Tenant;
use App\Models\TenantSubscription;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface SubscriptionRepositoryContract
{
    /** A assinatura mais recente do workspace, com o plano e os módulos dele. */
    public function current(Tenant $tenant): ?TenantSubscription;

    /**
     * Assinaturas de todos os workspaces que a rotina de cobrança precisa olhar agora: ciclo vencido,
     * teste grátis acabado ou atraso. Para o comando agendado, que roda fora de tenant.
     *
     * @return Collection<int, TenantSubscription>
     */
    public function needingAttention(CarbonInterface $now): Collection;
}
