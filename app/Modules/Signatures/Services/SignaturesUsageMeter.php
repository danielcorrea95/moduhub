<?php

namespace App\Modules\Signatures\Services;

use App\Models\Module;
use App\Models\Tenant;
use App\Modules\Signatures\Contracts\DocumentRepositoryContract;
use App\Platform\Contracts\UsageMeterContract;
use App\Platform\Usage\BillingPeriod;
use App\Platform\Usage\UsageMeter;

/**
 * Documentos enviados para assinatura no ciclo, contra a cota do plano ("documentos por mês").
 * Só mede: o envio ainda não é bloqueado ao passar da cota.
 */
class SignaturesUsageMeter implements UsageMeterContract
{
    public function __construct(
        private DocumentRepositoryContract $documents,
    ) {}

    public function meter(Tenant $tenant): ?UsageMeter
    {
        if (! $tenant->hasModule(Module::SIGNATURES)) {
            return null;
        }

        $subscription = $tenant->subscription()->withoutTenantScope()->with('plans.modules')->first();
        [$start, $end] = BillingPeriod::for($subscription);
        $terms = $subscription?->termsFor(Module::SIGNATURES);

        return new UsageMeter(
            key: Module::SIGNATURES,
            label: 'Assinaturas',
            used: $this->documents->countSentBetween($tenant->getKey(), $start, $end),
            limit: $terms?->included_quota,
            resetsAt: $end,
        );
    }
}
