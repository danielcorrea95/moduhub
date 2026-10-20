<?php

namespace App\Modules\Billing\Services;

use App\Models\TenantSubscription;
use App\Modules\Billing\Contracts\SubscriptionRepositoryContract;
use App\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;

/**
 * Rotina agendada da cobrança, para todos os workspaces:
 *  - fim do teste grátis: com assinatura, começa o primeiro ciclo; sem ela, bloqueia;
 *  - fim do ciclo: cobra o excedente do ciclo fechado e abre o próximo;
 *  - atraso além da carência: bloqueia.
 * Cada passo é idempotente; rodar duas vezes seguidas não cobra nem bloqueia em dobro.
 */
class BillingCycleService
{
    public function __construct(
        private SubscriptionRepositoryContract $subscriptions,
        private OverageBillingService $overage,
        private CurrentTenant $currentTenant,
    ) {}

    /** @return int quantas assinaturas foram olhadas */
    public function run(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $pending = $this->subscriptions->needingAttention($now);

        foreach ($pending as $subscription) {
            $this->currentTenant->run($subscription->tenant, fn () => $this->process($subscription, $now));
        }

        return $pending->count();
    }

    private function process(TenantSubscription $subscription, CarbonImmutable $now): void
    {
        if ($subscription->isTrial()) {
            $this->endTrial($subscription);
        }

        if ($subscription->hasGatewaySubscription() && ! $subscription->isTrial()) {
            $this->overage->retryPending($subscription);
            $this->closePeriods($subscription, $now);
        }

        $this->enforceGracePeriod($subscription, $now);
    }

    private function endTrial(TenantSubscription $subscription): void
    {
        if (! $subscription->hasGatewaySubscription()) {
            $subscription->update(['status' => TenantSubscription::STATUS_SUSPENDED, 'suspended_at' => now()]);

            return;
        }

        // A primeira mensalidade vence no fim do teste; se atrasar, o webhook põe em atraso.
        $start = CarbonImmutable::parse($subscription->trial_ends_at);
        $subscription->update([
            'status' => TenantSubscription::STATUS_ACTIVE,
            'current_period_start' => $start,
            'current_period_end' => $start->addMonthNoOverflow(),
        ]);
    }

    private function closePeriods(TenantSubscription $subscription, CarbonImmutable $now): void
    {
        while ($subscription->current_period_end !== null && $subscription->current_period_end->lte($now)) {
            $start = CarbonImmutable::parse($subscription->current_period_start);
            $end = CarbonImmutable::parse($subscription->current_period_end);

            // Ciclo que terminou até o fim do teste grátis (cancelou no teste) não tem excedente.
            $wasTrial = $subscription->trial_ends_at !== null && $end->lte($subscription->trial_ends_at);
            if (! $wasTrial) {
                $this->overage->bill($subscription->tenant, $subscription, $start, $end);
            }

            // Cancelada: o ciclo pago acabou e não há próximo. suspended_at marca que o fechamento já rodou.
            if ($subscription->status === TenantSubscription::STATUS_CANCELLED) {
                $subscription->update(['suspended_at' => now()]);

                return;
            }

            $subscription->update([
                'current_period_start' => $end,
                'current_period_end' => $end->addMonthNoOverflow(),
            ]);
        }
    }

    private function enforceGracePeriod(TenantSubscription $subscription, CarbonImmutable $now): void
    {
        $graceDays = (int) config('services.asaas.grace_days');

        if ($subscription->status === TenantSubscription::STATUS_PAST_DUE
            && $subscription->past_due_since !== null
            && $subscription->past_due_since->copy()->addDays($graceDays)->lte($now)) {
            $subscription->update(['status' => TenantSubscription::STATUS_SUSPENDED, 'suspended_at' => now()]);
        }
    }
}
