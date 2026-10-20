<?php

namespace App\Modules\Billing\Services;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Modules\Billing\Contracts\BillingAccountRepositoryContract;
use App\Modules\Billing\Contracts\PaymentGatewayContract;
use App\Modules\Billing\Contracts\SubscriptionRepositoryContract;
use App\Modules\Billing\Exceptions\PaymentGatewayException;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Services\Gateway\CardData;
use App\Platform\Services\PlanUsageService;
use App\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Assinatura do workspace no Asaas: assinar, trocar de plano ou de forma de pagamento e cancelar.
 * O Asaas é chamado antes de gravar aqui: se ele recusar, nada muda no Moduhub. Quem decide se está
 * pago é o webhook (BillingStandingService), nunca a resposta destas chamadas.
 */
class SubscriptionService
{
    public const TIMEZONE = 'America/Sao_Paulo';

    public function __construct(
        private PaymentGatewayContract $gateway,
        private SubscriptionRepositoryContract $subscriptions,
        private BillingAccountRepositoryContract $accounts,
        private PlanCart $cart,
        private CurrentTenant $currentTenant,
    ) {}

    /**
     * Assina o carrinho (um plano por produto) numa mensalidade só. No teste grátis, a primeira
     * mensalidade vence no fim do teste e o acesso segue liberado. Fora dele, vence hoje e o workspace
     * fica bloqueado até o pagamento cair.
     *
     * @param  list<int>  $planIds
     * @param  array<string, string|null>  $accountData
     */
    public function subscribe(
        Tenant $tenant,
        array $planIds,
        string $billingType,
        array $accountData,
        #[\SensitiveParameter] ?CardData $card,
        ?string $remoteIp,
    ): TenantSubscription {
        $plans = $this->cart->resolve($planIds);

        return $this->currentTenant->run($tenant, function () use ($tenant, $plans, $billingType, $accountData, $card, $remoteIp) {
            $current = $this->subscriptions->current($tenant);

            if ($current?->hasGatewaySubscription() && $current->status !== TenantSubscription::STATUS_CANCELLED) {
                throw ValidationException::withMessages(['plan_ids' => 'Este workspace já tem uma assinatura. Altere os planos ou a forma de pagamento.']);
            }

            $account = $this->saveAccount($accountData);
            $inTrial = $current?->isTrial() && $current->trial_ends_at?->isFuture();
            $firstDue = $inTrial
                ? CarbonImmutable::parse($current->trial_ends_at)->setTimezone(self::TIMEZONE)->startOfDay()
                : CarbonImmutable::now(self::TIMEZONE)->startOfDay();

            // Nosso id da assinatura vai como referência no Asaas; a linha nova nasce antes da chamada.
            $subscription = $inTrial ? $current : new TenantSubscription(['tenant_id' => $tenant->getKey()]);
            $subscription->id ??= $subscription->newUniqueId();

            $remote = $this->call(fn () => $this->gateway->createSubscription(
                $account,
                $billingType,
                PlanCart::total($plans),
                $firstDue,
                PlanCart::description($plans),
                $subscription->id,
                $card,
                $remoteIp,
            ));

            try {
                DB::transaction(function () use ($tenant, $subscription, $plans, $billingType, $remote, $inTrial) {
                    $subscription->fill([
                        'status' => $inTrial ? TenantSubscription::STATUS_TRIAL : TenantSubscription::STATUS_INCOMPLETE,
                        'billing_type' => $billingType,
                        'gateway_subscription_id' => $remote->id,
                        'card_brand' => $remote->cardBrand,
                        'card_last4' => $remote->cardLast4,
                        'card_token' => $remote->cardToken,
                        'cancelled_at' => null,
                        'past_due_since' => null,
                        'suspended_at' => null,
                    ])->save();

                    // Assinou o carrinho: o preço de cada item é o de hoje, inclusive dos planos do teste grátis.
                    $subscription->plans()->sync($plans->mapWithKeys(fn (Plan $plan) => [$plan->id => [
                        'tenant_id' => $tenant->getKey(),
                        'price_cents' => $plan->price_cents,
                    ]])->all());
                    $this->syncPlanModules($tenant, $plans);
                });
            } catch (\Throwable $e) {
                // Assinatura criada lá e não aqui: cobraria sem dar acesso. Desfaz no Asaas e devolve o erro.
                Log::error('Assinatura criada no Asaas sem gravar no Moduhub; cancelando lá.', ['tenant' => $tenant->getKey(), 'gateway_subscription_id' => $remote->id]);
                rescue(fn () => $this->gateway->cancelSubscription($remote->id));

                throw $e;
            }

            return $subscription->refresh();
        });
    }

    /**
     * Troca o carrinho: produto novo entra, produto tirado sai, troca de plano dentro do produto.
     * Os módulos mudam na hora; o valor novo vale a partir da próxima mensalidade em aberto, sem
     * cobrança proporcional. Plano que continua no carrinho mantém o preço contratado.
     *
     * @param  list<int>  $planIds
     */
    public function changePlans(Tenant $tenant, array $planIds): TenantSubscription
    {
        $plans = $this->cart->resolve($planIds);

        return $this->currentTenant->run($tenant, function () use ($tenant, $plans) {
            $subscription = $this->subscriptions->current($tenant);
            if ($subscription === null || $subscription->status === TenantSubscription::STATUS_CANCELLED) {
                throw ValidationException::withMessages(['plan_ids' => 'Assine um plano antes de trocar.']);
            }

            $kept = $subscription->plans->pluck('pivot.price_cents', 'id');
            $items = $plans->mapWithKeys(fn (Plan $plan) => [$plan->id => [
                'tenant_id' => $tenant->getKey(),
                'price_cents' => (int) ($kept[$plan->id] ?? $plan->price_cents),
            ]]);

            if ($subscription->hasGatewaySubscription()) {
                $total = (int) $items->sum('price_cents');
                $this->call(fn () => $this->gateway->updateSubscription($subscription->gateway_subscription_id, $total, PlanCart::description($plans)));
            }

            DB::transaction(function () use ($subscription, $tenant, $plans, $items) {
                $subscription->plans()->sync($items->all());
                $this->syncPlanModules($tenant, $plans);
            });

            return $subscription->refresh();
        });
    }

    /**
     * Pix ↔ cartão, ou cartão novo. Os dados de cobrança são atualizados junto, porque o titular do
     * cartão é o cliente cadastrado.
     *
     * @param  array<string, string|null>  $accountData
     */
    public function changePaymentMethod(
        Tenant $tenant,
        string $billingType,
        array $accountData,
        #[\SensitiveParameter] ?CardData $card,
        ?string $remoteIp,
    ): TenantSubscription {
        return $this->currentTenant->run($tenant, function () use ($tenant, $billingType, $accountData, $card, $remoteIp) {
            $subscription = $this->subscriptions->current($tenant);
            if (! $subscription?->hasGatewaySubscription() || $subscription->status === TenantSubscription::STATUS_CANCELLED) {
                throw ValidationException::withMessages(['billing_type' => 'Assine um plano antes de mudar a forma de pagamento.']);
            }

            $account = $this->saveAccount($accountData);

            $remote = $this->call(fn () => $this->gateway->changeSubscriptionPayment(
                $subscription->gateway_subscription_id,
                $account,
                $billingType,
                $card,
                $remoteIp,
            ));

            $byCard = $billingType === TenantSubscription::BILLING_CREDIT_CARD;
            $subscription->update([
                'billing_type' => $billingType,
                'card_brand' => $byCard ? $remote->cardBrand : null,
                'card_last4' => $byCard ? $remote->cardLast4 : null,
                'card_token' => $byCard ? $remote->cardToken : null,
            ]);

            return $subscription->refresh();
        });
    }

    /**
     * Encerra a recorrência no Asaas (que apaga as mensalidades em aberto). O workspace usa até o fim
     * do ciclo pago; o excedente desse ciclo ainda é cobrado no fechamento.
     */
    public function cancel(Tenant $tenant): TenantSubscription
    {
        return $this->currentTenant->run($tenant, function () use ($tenant) {
            $subscription = $this->subscriptions->current($tenant);
            if (! $subscription?->hasGatewaySubscription() || $subscription->status === TenantSubscription::STATUS_CANCELLED) {
                throw ValidationException::withMessages(['subscription' => 'Não há assinatura ativa para cancelar.']);
            }

            $this->call(fn () => $this->gateway->cancelSubscription($subscription->gateway_subscription_id));

            $subscription->update([
                'status' => TenantSubscription::STATUS_CANCELLED,
                'cancelled_at' => now(),
                // No teste grátis não há ciclo pago: o acesso acaba junto com o teste.
                'current_period_end' => $subscription->isTrial() ? $subscription->trial_ends_at : $subscription->current_period_end,
            ]);

            return $subscription->refresh();
        });
    }

    /**
     * Liga os módulos dos planos do carrinho e desliga os que vieram de plano que saiu. Módulo
     * contratado à parte (source diferente de "plan") não é tocado.
     *
     * @param  Collection<int, Plan>  $plans
     */
    public function syncPlanModules(Tenant $tenant, Collection $plans): void
    {
        $planModuleIds = $plans->flatMap(fn (Plan $plan) => $plan->modules->pluck('id'))->unique()->values()->all();

        $tenant->modules()
            ->wherePivot('source', 'plan')
            ->whereNotIn('modules.id', $planModuleIds)
            ->get()
            ->each(fn ($module) => $tenant->modules()->updateExistingPivot($module->id, ['is_active' => false]));

        foreach ($planModuleIds as $moduleId) {
            $tenant->modules()->syncWithoutDetaching([
                $moduleId => ['source' => 'plan', 'activated_at' => now(), 'is_active' => true],
            ]);
        }

        PlanUsageService::forget($tenant);
    }

    /** @param  array<string, string|null>  $data */
    private function saveAccount(array $data): BillingAccount
    {
        $account = $this->accounts->current() ?? new BillingAccount;
        $account->fill($data)->save();

        $customerId = $this->call(fn () => $this->gateway->saveCustomer($account));
        if ($account->gateway_customer_id !== $customerId) {
            $account->update(['gateway_customer_id' => $customerId]);
        }

        return $account;
    }

    /**
     * Recusa do Asaas (cartão negado, CPF inválido) vira erro de formulário.
     *
     * @template T
     *
     * @param  callable(): T  $call
     * @return T
     */
    private function call(callable $call): mixed
    {
        try {
            return $call();
        } catch (PaymentGatewayException $e) {
            throw ValidationException::withMessages(['gateway' => $e->getMessage()]);
        }
    }
}
