<?php

namespace App\Modules\Invoices\Services;

use App\Models\Module;
use App\Models\Tenant;
use App\Platform\Contracts\UsageMeterContract;
use App\Platform\Usage\UsageMeter;

/** Notas emitidas no ciclo, pela mesma conta da cota de NFS-e. */
class InvoicesUsageMeter implements UsageMeterContract
{
    public function __construct(
        private InvoiceQuotaService $quota,
    ) {}

    public function meter(Tenant $tenant): ?UsageMeter
    {
        if (! $tenant->hasModule(Module::INVOICES)) {
            return null;
        }

        $usage = $this->quota->usage($tenant);

        return new UsageMeter(
            key: Module::INVOICES,
            label: 'Notas fiscais',
            used: $usage->used,
            limit: $usage->included,
            resetsAt: $usage->periodEnd,
            detail: $usage->overageAllowed ? 'Passando da cota, as próximas entram como excedente.' : null,
        );
    }
}
