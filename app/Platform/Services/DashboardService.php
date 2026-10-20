<?php

namespace App\Platform\Services;

use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Platform\Contracts\DashboardSectionContract;
use App\Platform\Dashboard\DashboardAlert;
use App\Platform\Dashboard\DashboardSection;
use App\Platform\Usage\BillingPeriod;

/**
 * Monta a tela inicial: uma seção por módulo, os alertas de todos eles juntos (perigo primeiro) e,
 * para quem cuida da cobrança, quanto o ciclo vai custar.
 */
class DashboardService
{
    /** @param  iterable<DashboardSectionContract>  $sections */
    public function __construct(
        private iterable $sections,
    ) {}

    /** @return array{sections: array<string, array<string, mixed>>, alerts: list<array<string, string|null>>, billing: array<string, mixed>|null} */
    public function forTenant(Tenant $tenant, User $user): array
    {
        $sections = collect($this->sections)
            ->map(fn (DashboardSectionContract $provider) => $provider->section($tenant, $user))
            ->filter();

        return [
            'sections' => $sections->mapWithKeys(fn (DashboardSection $s) => [$s->key => $s->data])->all(),
            'alerts' => $sections
                ->flatMap(fn (DashboardSection $s) => $s->alerts)
                ->sortBy(fn (DashboardAlert $a) => $a->tone === DashboardAlert::DANGER ? 0 : 1)
                ->map(fn (DashboardAlert $a) => $a->toArray())
                ->values()
                ->all(),
            'billing' => $user->can('manageBilling', $tenant) ? $this->billing($tenant, $sections->flatMap(fn (DashboardSection $s) => $s->charges)->all()) : null,
        ];
    }

    /**
     * Previsão do ciclo atual: o plano mais o excedente já consumido. Não é a fatura; impostos e
     * descontos ficam com a cobrança.
     *
     * @param  list<array{label: string, cents: int}>  $charges
     * @return array<string, mixed>
     */
    private function billing(Tenant $tenant, array $charges): array
    {
        // A relação já filtra pelo tenant recebido.
        $subscription = $tenant->subscription()->withoutTenantScope()->with('plan')->first();
        $plan = $subscription?->plan;
        $trial = $subscription?->status === TenantSubscription::STATUS_TRIAL;
        [$start, $end] = BillingPeriod::for($subscription);

        $lines = array_values(array_filter([
            $plan ? ['label' => "Plano {$plan->name}", 'cents' => $trial ? 0 : (int) $plan->price_cents] : null,
            ...$charges,
        ]));

        return [
            'plan' => $plan?->name,
            'status' => $subscription?->status,
            'trial_ends_at' => $trial ? $subscription->trial_ends_at?->toIso8601String() : null,
            'period_start' => $start->toIso8601String(),
            'period_end' => $end->toIso8601String(),
            'lines' => $lines,
            'total_cents' => array_sum(array_column($lines, 'cents')),
        ];
    }
}
