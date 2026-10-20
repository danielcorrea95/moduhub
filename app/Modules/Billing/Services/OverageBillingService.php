<?php

namespace App\Modules\Billing\Services;

use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Modules\Billing\Contracts\BillingAccountRepositoryContract;
use App\Modules\Billing\Contracts\BillingPaymentRepositoryContract;
use App\Modules\Billing\Contracts\PaymentGatewayContract;
use App\Modules\Billing\Exceptions\PaymentGatewayException;
use App\Modules\Billing\Models\BillingPayment;
use App\Platform\Contracts\BillableUsageContract;
use App\Platform\Usage\BillableLine;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * Cobrança avulsa do excedente de um ciclo fechado. Primeiro grava aqui (uma por ciclo, garantido
 * pelo índice único), depois manda ao Asaas; se o Asaas falhar, a rotina tenta de novo na próxima
 * execução. Roda dentro do tenant da assinatura.
 */
class OverageBillingService
{
    /** O Asaas não aceita cobrança abaixo de R$ 5,00; excedente menor que isso não é cobrado. */
    public const MIN_CHARGE_CENTS = 500;

    /** Pix e fatura vencem alguns dias depois do fechamento; no cartão, o Asaas cobra na data. */
    private const DAYS_TO_PAY = 3;

    /** @param  iterable<BillableUsageContract>  $usage */
    public function __construct(
        private iterable $usage,
        private PaymentGatewayContract $gateway,
        private BillingPaymentRepositoryContract $payments,
        private BillingAccountRepositoryContract $accounts,
    ) {}

    public function bill(Tenant $tenant, TenantSubscription $subscription, CarbonInterface $start, CarbonInterface $end): ?BillingPayment
    {
        if ($subscription->plans->isEmpty() || $this->payments->overageFor($start) !== null) {
            return null;
        }

        $lines = collect($this->usage)
            ->flatMap(fn (BillableUsageContract $module) => $module->overage($tenant, $subscription, $start, $end))
            ->values();
        $total = $lines->sum(fn (BillableLine $line) => $line->totalCents());

        if ($total < self::MIN_CHARGE_CENTS) {
            return null;
        }

        $byCard = $subscription->billing_type === TenantSubscription::BILLING_CREDIT_CARD && $subscription->card_token !== null;

        $payment = BillingPayment::create([
            'tenant_subscription_id' => $subscription->getKey(),
            'kind' => BillingPayment::KIND_OVERAGE,
            'billing_type' => $subscription->billing_type,
            'status' => BillingPayment::STATUS_PENDING,
            'value_cents' => $total,
            'due_date' => CarbonImmutable::now(SubscriptionService::TIMEZONE)->addDays($byCard ? 0 : self::DAYS_TO_PAY)->format('Y-m-d'),
            'description' => sprintf(
                'Moduhub - excedente de %s a %s',
                CarbonImmutable::parse($start)->setTimezone(SubscriptionService::TIMEZONE)->format('d/m'),
                CarbonImmutable::parse($end)->setTimezone(SubscriptionService::TIMEZONE)->subDay()->format('d/m/Y'),
            ),
            'period_start' => $start,
            'period_end' => $end,
            'lines' => $lines->map(fn (BillableLine $line) => $line->toArray())->all(),
        ]);

        $this->send($payment, $subscription);

        return $payment;
    }

    /** Excedentes gravados que o Asaas ainda não recebeu (falha na chamada anterior). */
    public function retryPending(TenantSubscription $subscription): void
    {
        $this->payments->overagesNotSent()->each(fn (BillingPayment $payment) => $this->send($payment, $subscription));
    }

    private function send(BillingPayment $payment, TenantSubscription $subscription): void
    {
        $account = $this->accounts->current();
        if ($account?->gateway_customer_id === null) {
            return;
        }

        try {
            $remote = $this->gateway->createCharge(
                $account,
                (string) $subscription->billing_type,
                $payment->value_cents,
                CarbonImmutable::parse($payment->due_date->format('Y-m-d')),
                (string) $payment->description,
                $payment->getKey(),
                $subscription->billing_type === TenantSubscription::BILLING_CREDIT_CARD ? $subscription->card_token : null,
            );
        } catch (PaymentGatewayException $e) {
            Log::warning('Excedente não chegou ao Asaas; nova tentativa na próxima rodada.', ['payment' => $payment->getKey(), 'error' => $e->getMessage()]);
            $payment->update(['failure_reason' => $e->getMessage()]);

            return;
        }

        $payment->update([
            'gateway_payment_id' => $remote->id,
            'billing_type' => $remote->billingType,
            'invoice_url' => $remote->invoiceUrl,
            'failure_reason' => null,
        ]);
    }
}
