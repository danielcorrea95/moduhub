<?php

namespace App\Modules\Signatures\Services;

use App\Models\Module;
use App\Models\TenantSubscription;
use App\Models\Tenant;
use App\Modules\Signatures\Contracts\DocumentRepositoryContract;
use App\Platform\Contracts\BillableUsageContract;
use App\Platform\Usage\BillableLine;
use Carbon\CarbonInterface;

/** Documentos enviados para assinatura além da cota do plano no ciclo. */
class SignaturesBillableUsage implements BillableUsageContract
{
    public function __construct(
        private DocumentRepositoryContract $documents,
    ) {}

    public function overage(Tenant $tenant, TenantSubscription $subscription, CarbonInterface $start, CarbonInterface $end): array
    {
        $pivot = $subscription->termsFor(Module::SIGNATURES);
        if ($pivot?->included_quota === null || $pivot->overage_cents === null) {
            return [];
        }

        $extra = $this->documents->countSentBetween($tenant->getKey(), $start, $end) - (int) $pivot->included_quota;

        return $extra > 0 ? [new BillableLine($extra === 1 ? '1 documento excedente' : "{$extra} documentos excedentes", $extra, (int) $pivot->overage_cents)] : [];
    }
}
