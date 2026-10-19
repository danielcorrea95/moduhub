<?php

namespace App\Modules\Invoices\Services;

use App\Models\Module;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Modules\Invoices\Contracts\InvoiceRepositoryContract;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Cota de notas do plano (plan_module.included_quota). Conta toda DPS que gerou ou pode gerar NFS-e
 * no período da assinatura; rejeitadas não contam. Com preço de excedente a emissão segue e a nota
 * fica marcada para cobrança; sem ele, ou no teste grátis, a cota é limite.
 */
class InvoiceQuotaService
{
    public function __construct(protected InvoiceRepositoryContract $invoices) {}

    public function usage(Tenant $tenant): InvoiceUsage
    {
        $subscription = $tenant->subscription()->with('plan.modules')->first();
        [$start, $end] = $this->period($subscription);

        $module = $subscription?->plan?->modules->firstWhere('key', Module::INVOICES);
        $included = $module?->pivot?->included_quota;
        $overage = $module?->pivot?->overage_cents;

        return new InvoiceUsage(
            used: $this->invoices->countBilledBetween($tenant->getKey(), $start, $end),
            included: $included !== null ? (int) $included : null,
            overageCents: $overage !== null ? (int) $overage : null,
            overageAllowed: $overage !== null && $subscription?->status === TenantSubscription::STATUS_ACTIVE,
            periodStart: $start,
            periodEnd: $end,
            planName: $subscription?->plan?->name,
        );
    }

    /**
     * Chamado antes de reservar o número da DPS.
     *
     * @return bool se esta nota é excedente
     */
    public function reserve(Tenant $tenant): bool
    {
        $usage = $this->usage($tenant);
        if (! $usage->exhausted()) {
            return false;
        }

        if (! $usage->overageAllowed) {
            throw ValidationException::withMessages([
                'quota' => "A cota de {$usage->included} notas do plano neste período acabou (renova em {$usage->periodEnd->format('d/m/Y')}). Mude de plano para continuar emitindo.",
            ]);
        }

        return true;
    }

    /** @return array{0: Carbon, 1: Carbon} período da assinatura, ou o mês corrente sem assinatura */
    protected function period(?TenantSubscription $subscription): array
    {
        $start = $subscription?->current_period_start;
        $end = $subscription?->current_period_end;

        if ($start === null || $end === null || now()->greaterThanOrEqualTo($end)) {
            return [now()->startOfMonth(), now()->endOfMonth()];
        }

        return [Carbon::parse($start), Carbon::parse($end)];
    }
}
