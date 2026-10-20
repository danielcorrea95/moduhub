<?php

namespace App\Modules\Billing\Services;

use App\Models\TenantSubscription;
use App\Modules\Billing\Contracts\BillingAccountRepositoryContract;
use App\Modules\Billing\Contracts\BillingPaymentRepositoryContract;
use App\Modules\Billing\Contracts\PaymentGatewayContract;
use App\Modules\Billing\Contracts\SubscriptionRepositoryContract;
use App\Modules\Billing\Models\BillingPayment;
use App\Modules\Billing\Models\BillingWebhookEvent;
use App\Modules\Billing\Services\Gateway\GatewayPayment;
use App\Platform\Services\PlanUsageService;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Eventos de cobrança do Asaas. A entrega é "pelo menos uma vez" e fora de ordem: o evento é guardado
 * pelo id e a cobrança é sempre regravada com o estado que veio, então repetir não muda nada.
 */
class AsaasWebhookService
{
    public function __construct(
        private PaymentGatewayContract $gateway,
        private BillingAccountRepositoryContract $accounts,
        private BillingPaymentRepositoryContract $payments,
        private SubscriptionRepositoryContract $subscriptions,
        private BillingStandingService $standing,
        private CurrentTenant $currentTenant,
    ) {}

    /** @param  array<string, mixed>  $payload */
    public function handle(array $payload): void
    {
        $event = BillingWebhookEvent::firstOrCreate(
            ['gateway_event_id' => (string) ($payload['id'] ?? sha1(json_encode($payload)))],
            ['event' => (string) ($payload['event'] ?? ''), 'payload' => $payload],
        );

        if ($event->processed_at !== null) {
            return;
        }

        if (str_starts_with($event->event, 'PAYMENT_') && is_array($payload['payment'] ?? null)) {
            $this->handlePayment($event->event, $this->gateway->parsePayment($payload['payment']));
        }

        $event->update(['processed_at' => now()]);
    }

    private function handlePayment(string $event, GatewayPayment $remote): void
    {
        $account = $this->accounts->findByGatewayCustomer($remote->customerId);

        // Cobrança de cliente que não é de nenhum workspace (outra integração na mesma conta do Asaas).
        if ($account === null) {
            Log::info('Webhook do Asaas para cliente desconhecido.', ['customer' => $remote->customerId, 'payment' => $remote->id]);

            return;
        }

        $this->currentTenant->run($account->tenant, function () use ($account, $event, $remote) {
            DB::transaction(function () use ($account, $event, $remote) {
                $subscription = $this->subscriptions->current($account->tenant);
                $payment = $this->savePayment($event, $remote, $subscription);

                if ($subscription !== null) {
                    $this->standing->refresh($subscription, $payment);
                }
            });

            PlanUsageService::forget($account->tenant);
        });
    }

    private function savePayment(string $event, GatewayPayment $remote, ?TenantSubscription $subscription): BillingPayment
    {
        $payment = $this->payments->findForGateway($remote->externalReference, $remote->id) ?? new BillingPayment([
            'kind' => BillingPayment::KIND_SUBSCRIPTION,
            'tenant_subscription_id' => $remote->subscriptionId !== null && $remote->subscriptionId === $subscription?->gateway_subscription_id
                ? $subscription->getKey()
                : null,
        ]);

        $failure = match ($event) {
            'PAYMENT_CREDIT_CARD_CAPTURE_REFUSED' => 'Cartão recusado pela operadora.',
            'PAYMENT_REPROVED_BY_RISK_ANALYSIS' => 'Pagamento reprovado na análise de risco.',
            default => $remote->status === BillingPayment::STATUS_PAID ? null : $payment->failure_reason,
        };

        $payment->fill([
            'gateway_payment_id' => $remote->id,
            'billing_type' => $remote->billingType,
            'status' => $event === 'PAYMENT_DELETED' ? BillingPayment::STATUS_CANCELLED : $remote->status,
            'value_cents' => $remote->valueCents,
            'due_date' => $remote->dueDate,
            'paid_at' => $remote->status === BillingPayment::STATUS_PAID ? ($remote->paidAt ?? now()) : null,
            'invoice_url' => $remote->invoiceUrl,
            'description' => $payment->description ?? $remote->description,
            'failure_reason' => $failure,
        ])->save();

        return $payment;
    }
}
