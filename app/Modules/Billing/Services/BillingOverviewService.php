<?php

namespace App\Modules\Billing\Services;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Modules\Billing\Contracts\BillingAccountRepositoryContract;
use App\Modules\Billing\Contracts\BillingPaymentRepositoryContract;
use App\Modules\Billing\Contracts\PaymentGatewayContract;
use App\Modules\Billing\Contracts\SubscriptionRepositoryContract;
use App\Modules\Billing\Exceptions\PaymentGatewayException;
use App\Modules\Billing\Models\BillingPayment;
use App\Platform\Contracts\PlanRepositoryContract;
use App\Platform\Services\PlanCatalogService;
use Illuminate\Validation\ValidationException;

/** Dados da tela Assinatura e do aviso de cobrança que aparece no topo do painel. */
class BillingOverviewService
{
    private const PAYMENTS_SHOWN = 24;

    /** Começa a avisar do fim do teste grátis com esta antecedência. */
    private const TRIAL_WARNING_DAYS = 7;

    public function __construct(
        private SubscriptionRepositoryContract $subscriptions,
        private BillingAccountRepositoryContract $accounts,
        private BillingPaymentRepositoryContract $payments,
        private PlanRepositoryContract $plans,
        private PaymentGatewayContract $gateway,
        private PlanCatalogService $catalog,
    ) {}

    /**
     * @param  list<string>  $planKeys  carrinho montado na página de vendas, já marcado na tela
     * @return array<string, mixed>
     */
    public function forTenant(Tenant $tenant, User $user, array $planKeys = []): array
    {
        $subscription = $this->subscriptions->current($tenant);
        $account = $this->accounts->current();
        $preselected = $planKeys !== [] ? $this->plans->findManyForSaleByKeys($planKeys)->pluck('id')->all() : [];

        return [
            'subscription' => $subscription ? $this->subscription($subscription) : null,
            'products' => array_values(array_filter($this->catalog->products(), fn (array $p) => $p['released'])),
            'account' => [
                'name' => $account->name ?? $tenant->name,
                'tax_id' => $account->tax_id ?? preg_replace('/\D/', '', (string) $tenant->tax_id),
                'email' => $account->email ?? $user->email,
                'phone' => $account->phone ?? '',
                'postal_code' => $account->postal_code ?? '',
                'address_number' => $account->address_number ?? '',
                'address_complement' => $account->address_complement ?? '',
            ],
            'payments' => $this->payments->latest(self::PAYMENTS_SHOWN)->map(fn (BillingPayment $p) => [
                'id' => $p->getKey(),
                'kind' => $p->kind,
                'description' => $p->description,
                'status' => $p->status,
                'billing_type' => $p->billing_type,
                'value_cents' => $p->value_cents,
                'due_date' => $p->due_date->format('Y-m-d'),
                'paid_at' => $p->paid_at?->toIso8601String(),
                'invoice_url' => $p->invoice_url,
                'failure_reason' => $p->failure_reason,
                'lines' => $p->lines,
                'has_pix' => $p->isOpen() && $p->gateway_payment_id !== null && $p->billing_type === TenantSubscription::BILLING_PIX,
            ])->values(),
            'grace_days' => (int) config('services.asaas.grace_days'),
            'preselected_plan_ids' => $preselected,
        ];
    }

    /**
     * Aviso para o topo do painel, ou nulo quando está tudo em dia. Quem não cuida da cobrança só vê
     * o bloqueio, sem os detalhes.
     *
     * @return array<string, mixed>|null
     */
    public function notice(Tenant $tenant, User $user): ?array
    {
        $subscription = $this->subscriptions->current($tenant);
        if ($subscription === null) {
            return null;
        }

        $blocked = $subscription->isBlocked();
        $trialEnding = $subscription->isTrial()
            && ! $subscription->hasGatewaySubscription()
            && $subscription->trial_ends_at?->lte(now()->addDays(self::TRIAL_WARNING_DAYS));
        $pastDue = $subscription->status === TenantSubscription::STATUS_PAST_DUE;
        $cancelled = $subscription->status === TenantSubscription::STATUS_CANCELLED && ! $blocked;

        if (! $blocked && ! $trialEnding && ! $pastDue && ! $cancelled) {
            return null;
        }

        return [
            'blocked' => $blocked,
            'status' => $subscription->status,
            'trial_ends_at' => $trialEnding ? $subscription->trial_ends_at->toIso8601String() : null,
            'grace_ends_at' => $pastDue ? $this->graceEndsAt($subscription) : null,
            'access_ends_at' => $cancelled ? $subscription->current_period_end?->toIso8601String() : null,
            'can_manage' => $user->can('manageBilling', $tenant),
        ];
    }

    /** @return array{image: string, payload: string, expires_at: string|null} */
    public function pix(BillingPayment $payment): array
    {
        if (! $payment->isOpen() || $payment->gateway_payment_id === null) {
            throw ValidationException::withMessages(['payment' => 'Esta cobrança não está em aberto.']);
        }

        try {
            $qr = $this->gateway->pixQrCode($payment->gateway_payment_id);
        } catch (PaymentGatewayException $e) {
            throw ValidationException::withMessages(['payment' => $e->getMessage()]);
        }

        return ['image' => $qr->image, 'payload' => $qr->payload, 'expires_at' => $qr->expiresAt];
    }

    /** @return array<string, mixed> */
    private function subscription(TenantSubscription $s): array
    {
        return [
            'status' => $s->status,
            'blocked' => $s->isBlocked(),
            'plans' => $s->plans->map(fn (Plan $plan) => [
                ...PlanCatalogService::plan($plan),
                // O que este workspace paga, que pode ser diferente do preço de hoje.
                'price_cents' => (int) $plan->pivot->price_cents,
            ])->values(),
            'price_cents' => $s->priceCents(),
            'billing_type' => $s->billing_type,
            'has_gateway_subscription' => $s->hasGatewaySubscription(),
            'card_brand' => $s->card_brand,
            'card_last4' => $s->card_last4,
            'trial_ends_at' => $s->trial_ends_at?->toIso8601String(),
            'current_period_start' => $s->current_period_start?->toIso8601String(),
            'current_period_end' => $s->current_period_end?->toIso8601String(),
            'cancelled_at' => $s->cancelled_at?->toIso8601String(),
            'past_due_since' => $s->past_due_since?->format('Y-m-d'),
            'grace_ends_at' => $s->status === TenantSubscription::STATUS_PAST_DUE ? $this->graceEndsAt($s) : null,
        ];
    }

    private function graceEndsAt(TenantSubscription $s): ?string
    {
        return $s->past_due_since?->copy()->addDays((int) config('services.asaas.grace_days'))->format('Y-m-d');
    }
}
