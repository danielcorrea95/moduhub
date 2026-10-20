<?php

namespace App\Modules\Billing\Services\Gateway;

use App\Modules\Billing\Contracts\PaymentGatewayContract;
use App\Modules\Billing\Exceptions\PaymentGatewayException;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\BillingPayment;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Asaas de mentira para dev e testes. Cartão terminado em 0002 é recusado, como no sandbox.
 * Guarda as chamadas para os testes conferirem o que teria ido para o Asaas.
 */
class FakePaymentGateway implements PaymentGatewayContract
{
    public const REFUSED_CARD_SUFFIX = '0002';

    /** @var array<string, array<string, mixed>> */
    public array $customers = [];

    /** @var array<string, array<string, mixed>> */
    public array $subscriptions = [];

    /** @var array<string, array<string, mixed>> */
    public array $charges = [];

    /** Simula a conta sem tokenização habilitada. */
    public bool $tokenization = true;

    public function saveCustomer(BillingAccount $account): string
    {
        $id = $account->gateway_customer_id ?? 'cus_'.Str::random(12);
        $this->customers[$id] = $account->only(['name', 'tax_id', 'email']);

        return $id;
    }

    public function createSubscription(
        BillingAccount $account,
        string $billingType,
        int $valueCents,
        CarbonInterface $firstDueDate,
        string $description,
        string $externalReference,
        #[\SensitiveParameter] ?CardData $card,
        ?string $remoteIp,
    ): GatewaySubscription {
        $this->refuse($card);

        $id = 'sub_'.Str::random(12);
        $this->subscriptions[$id] = [
            'customer' => $account->gateway_customer_id,
            'billingType' => $billingType,
            'value' => $valueCents,
            'nextDueDate' => $firstDueDate->format('Y-m-d'),
            'description' => $description,
            'externalReference' => $externalReference,
            'deleted' => false,
        ];

        return $this->subscription($id, $card);
    }

    public function updateSubscription(string $subscriptionId, int $valueCents, string $description): void
    {
        $this->subscriptions[$subscriptionId]['value'] = $valueCents;
        $this->subscriptions[$subscriptionId]['description'] = $description;
    }

    public function changeSubscriptionPayment(
        string $subscriptionId,
        BillingAccount $account,
        string $billingType,
        #[\SensitiveParameter] ?CardData $card,
        ?string $remoteIp,
    ): GatewaySubscription {
        $this->refuse($card);
        $this->subscriptions[$subscriptionId]['billingType'] = $billingType;

        return $this->subscription($subscriptionId, $card);
    }

    public function cancelSubscription(string $subscriptionId): void
    {
        $this->subscriptions[$subscriptionId]['deleted'] = true;
    }

    public function createCharge(
        BillingAccount $account,
        string $billingType,
        int $valueCents,
        CarbonInterface $dueDate,
        string $description,
        string $externalReference,
        #[\SensitiveParameter] ?string $cardToken,
    ): GatewayPayment {
        $id = 'pay_'.Str::random(12);
        $this->charges[$id] = compact('billingType', 'valueCents', 'description', 'externalReference', 'cardToken') + [
            'customer' => $account->gateway_customer_id,
            'dueDate' => $dueDate->format('Y-m-d'),
        ];

        return new GatewayPayment(
            id: $id,
            customerId: (string) $account->gateway_customer_id,
            subscriptionId: null,
            externalReference: $externalReference,
            billingType: $billingType,
            status: BillingPayment::STATUS_PENDING,
            valueCents: $valueCents,
            dueDate: $dueDate->format('Y-m-d'),
            paidAt: null,
            invoiceUrl: "https://sandbox.asaas.com/i/{$id}",
            description: $description,
        );
    }

    public function pixQrCode(string $paymentId): PixQrCode
    {
        // PNG 1x1 transparente: a tela tem o que mostrar em dev.
        return new PixQrCode(
            image: 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
            payload: "00020101021226820014br.gov.bcb.pix2560fake.asaas.com/{$paymentId}5204000053039865802BR6304ABCD",
            expiresAt: now()->addDay()->format('Y-m-d H:i:s'),
        );
    }

    public function parsePayment(array $payload): GatewayPayment
    {
        return (new AsaasPaymentGateway('', ''))->parsePayment($payload);
    }

    private function refuse(#[\SensitiveParameter] ?CardData $card): void
    {
        if ($card !== null && str_ends_with($card->number, self::REFUSED_CARD_SUFFIX)) {
            throw new PaymentGatewayException('Transação não autorizada. Verifique os dados do cartão ou use outro cartão.', 'invalid_creditCard');
        }
    }

    private function subscription(string $id, #[\SensitiveParameter] ?CardData $card): GatewaySubscription
    {
        return new GatewaySubscription(
            id: $id,
            cardBrand: $card ? 'VISA' : null,
            cardLast4: $card ? substr($card->number, -4) : null,
            cardToken: $card && $this->tokenization ? 'tok_'.Str::random(16) : null,
        );
    }
}
