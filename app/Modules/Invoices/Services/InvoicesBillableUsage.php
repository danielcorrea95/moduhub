<?php

namespace App\Modules\Invoices\Services;

use App\Models\Module;
use App\Models\TenantSubscription;
use App\Models\Tenant;
use App\Modules\Invoices\Contracts\InvoiceRepositoryContract;
use App\Platform\Contracts\BillableUsageContract;
use App\Platform\Usage\BillableLine;
use Carbon\CarbonInterface;

/** Notas emitidas além da cota no ciclo: já saem marcadas (is_overage) na emissão. */
class InvoicesBillableUsage implements BillableUsageContract
{
    public function __construct(
        private InvoiceRepositoryContract $invoices,
    ) {}

    public function overage(Tenant $tenant, TenantSubscription $subscription, CarbonInterface $start, CarbonInterface $end): array
    {
        $unit = $subscription->termsFor(Module::INVOICES)?->overage_cents;
        if ($unit === null) {
            return [];
        }

        $count = $this->invoices->countOverageBetween($tenant->getKey(), $start, $end);

        return $count > 0 ? [new BillableLine($count === 1 ? '1 nota excedente' : "{$count} notas excedentes", $count, (int) $unit)] : [];
    }
}
