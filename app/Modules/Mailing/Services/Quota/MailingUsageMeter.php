<?php

namespace App\Modules\Mailing\Services\Quota;

use App\Models\Module;
use App\Models\Tenant;
use App\Modules\Mailing\Contracts\SendingDomainRepositoryContract;
use App\Platform\Contracts\UsageMeterContract;
use App\Platform\Usage\UsageMeter;

/**
 * Disparo do mês. Aparece para quem tem o módulo no plano ou já pode usar a cota gratuita
 * (domínio de envio verificado).
 */
class MailingUsageMeter implements UsageMeterContract
{
    public function __construct(
        private EmailQuotaService $quota,
        private SendingDomainRepositoryContract $domains,
    ) {}

    public function meter(Tenant $tenant): ?UsageMeter
    {
        if (! $tenant->hasModule(Module::MAILING) && ! $this->domains->forTenant($tenant)?->isVerified()) {
            return null;
        }

        $usage = $this->quota->usage($tenant);
        $today = $usage->dailyLimit === null
            ? "Hoje: {$usage->dailyUsed} enviados"
            : "Hoje: {$usage->dailyUsed} de {$usage->dailyLimit}";

        return new UsageMeter(
            key: Module::MAILING,
            label: 'E-mails',
            used: $usage->monthlyUsed,
            limit: $usage->monthlyLimit,
            resetsAt: $usage->periodEnd,
            detail: $today,
        );
    }
}
