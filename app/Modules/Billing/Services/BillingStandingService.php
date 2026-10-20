<?php

namespace App\Modules\Billing\Services;

use App\Models\TenantSubscription;
use App\Modules\Billing\Contracts\BillingPaymentRepositoryContract;
use App\Modules\Billing\Models\BillingPayment;
use Carbon\CarbonImmutable;

/**
 * Situação da assinatura a partir das cobranças: qualquer cobrança em atraso (mensalidade ou
 * excedente) deixa em atraso; sem atraso, volta a ativa. Recalcula do zero a cada evento, então a
 * ordem e a repetição dos webhooks não importam. Roda dentro do tenant da assinatura.
 */
class BillingStandingService
{
    public function __construct(
        private BillingPaymentRepositoryContract $payments,
    ) {}

    public function refresh(TenantSubscription $subscription, ?BillingPayment $trigger = null): void
    {
        if ($subscription->status === TenantSubscription::STATUS_CANCELLED || ! $subscription->hasGatewaySubscription()) {
            return;
        }

        $overdueSince = $this->payments->oldestOverdueDueDate();

        if ($overdueSince !== null) {
            $subscription->past_due_since ??= $overdueSince;
            if ($subscription->status === TenantSubscription::STATUS_ACTIVE) {
                $subscription->status = TenantSubscription::STATUS_PAST_DUE;
            }
            $subscription->save();

            return;
        }

        $subscription->past_due_since = null;

        if (in_array($subscription->status, [TenantSubscription::STATUS_PAST_DUE, TenantSubscription::STATUS_SUSPENDED], true)) {
            $subscription->status = TenantSubscription::STATUS_ACTIVE;
            $subscription->suspended_at = null;
        }

        // Assinou depois do teste: o ciclo começa no vencimento da primeira mensalidade paga.
        if ($subscription->status === TenantSubscription::STATUS_INCOMPLETE
            && $this->payments->hasPaidSubscriptionPayment($subscription->getKey())) {
            $start = $trigger?->kind === BillingPayment::KIND_SUBSCRIPTION && $trigger->status === BillingPayment::STATUS_PAID
                ? CarbonImmutable::parse($trigger->due_date->format('Y-m-d'), SubscriptionService::TIMEZONE)
                : CarbonImmutable::now(SubscriptionService::TIMEZONE)->startOfDay();

            $subscription->fill([
                'status' => TenantSubscription::STATUS_ACTIVE,
                'current_period_start' => $start,
                'current_period_end' => $start->addMonthNoOverflow(),
            ]);
        }

        $subscription->save();
    }
}
