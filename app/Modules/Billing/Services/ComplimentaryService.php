<?php

namespace App\Modules\Billing\Services;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Modules\Billing\Contracts\SubscriptionRepositoryContract;
use App\Platform\Contracts\PlanRepositoryContract;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cortesia: workspace que usa sem pagar, dado pelo admin da plataforma (sistemas da própria casa,
 * parceiros). Não passa pelo Asaas, não tem ciclo nem cobrança de excedente e nunca bloqueia por
 * pagamento. Os planos só dão as cotas; para uso sem limite, um plano oculto do site sem cota.
 */
class ComplimentaryService
{
    /** Ao encerrar a cortesia, o workspace ganha este teste grátis para assinar sem perder o acesso. */
    public const ENDING_TRIAL_DAYS = 7;

    public function __construct(
        private SubscriptionRepositoryContract $subscriptions,
        private PlanRepositoryContract $plans,
        private SubscriptionService $subscriptionService,
        private CurrentTenant $currentTenant,
    ) {}

    /**
     * Dá a cortesia ou troca os planos de quem já tem. Plano oculto do site também vale, para a
     * equipe ter um plano "sem limite" só para cortesia.
     *
     * @param  list<int>  $planIds
     */
    public function grant(Tenant $tenant, array $planIds): TenantSubscription
    {
        $plans = $this->resolve($planIds);

        return $this->currentTenant->run($tenant, function () use ($tenant, $plans) {
            $current = $this->subscriptions->current($tenant);

            // Assinatura viva no Asaas seguiria cobrando: o cliente cancela antes, na tela Assinatura.
            if ($current?->hasGatewaySubscription() && $current->status !== TenantSubscription::STATUS_CANCELLED) {
                throw ValidationException::withMessages(['plan_ids' => 'Este workspace tem assinatura paga no Asaas. Cancele a assinatura antes de dar cortesia.']);
            }

            return DB::transaction(function () use ($tenant, $plans, $current) {
                // Assinatura cancelada fica no histórico; a cortesia começa numa linha nova.
                $subscription = $current !== null && ! $current->hasGatewaySubscription()
                    ? $current
                    : new TenantSubscription(['tenant_id' => $tenant->getKey()]);

                $subscription->fill([
                    'status' => TenantSubscription::STATUS_COMPLIMENTARY,
                    'trial_ends_at' => null,
                    // Sem ciclo, a cota mensal segue o mês do calendário (BillingPeriod).
                    'current_period_start' => null,
                    'current_period_end' => null,
                    'cancelled_at' => null,
                    'past_due_since' => null,
                    'suspended_at' => null,
                ])->save();

                $subscription->plans()->sync($plans->mapWithKeys(fn (Plan $plan) => [$plan->id => [
                    'tenant_id' => $tenant->getKey(),
                    'price_cents' => 0,
                ]])->all());
                $this->subscriptionService->syncPlanModules($tenant, $plans);

                return $subscription->refresh();
            });
        });
    }

    /**
     * Tira a cortesia. Os produtos continuam ligados num teste grátis curto, com o aviso de sempre no
     * topo do painel, para o cliente assinar sem ficar bloqueado de surpresa.
     */
    public function end(Tenant $tenant): TenantSubscription
    {
        return $this->currentTenant->run($tenant, function () use ($tenant) {
            $subscription = $this->subscriptions->current($tenant);
            if (! $subscription?->isComplimentary()) {
                throw ValidationException::withMessages(['subscription' => 'Este workspace não está em cortesia.']);
            }

            $endsAt = now()->addDays(self::ENDING_TRIAL_DAYS);
            $subscription->update([
                'status' => TenantSubscription::STATUS_TRIAL,
                'trial_ends_at' => $endsAt,
                'current_period_start' => now(),
                'current_period_end' => $endsAt,
            ]);

            return $subscription->refresh();
        });
    }

    /**
     * @param  list<int>  $planIds
     * @return Collection<int, Plan>
     */
    private function resolve(array $planIds): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $planIds)));
        $plans = $this->plans->active($ids);

        if ($plans->isEmpty()) {
            throw ValidationException::withMessages(['plan_ids' => 'Escolha pelo menos um plano.']);
        }

        if ($plans->count() !== count($ids)) {
            throw ValidationException::withMessages(['plan_ids' => 'Um dos planos escolhidos foi desativado. Recarregue a página.']);
        }

        if ($plans->flatMap(fn (Plan $plan) => $plan->modules)->pluck('key')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['plan_ids' => 'Escolha só um plano de cada produto.']);
        }

        return $plans;
    }
}
