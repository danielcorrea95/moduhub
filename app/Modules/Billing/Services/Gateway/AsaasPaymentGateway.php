<?php

namespace App\Modules\Billing\Services\Gateway;

use App\Modules\Billing\Contracts\PaymentGatewayContract;
use App\Modules\Billing\Exceptions\PaymentGatewayException;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\BillingPayment;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * API v3 do Asaas. O cartão vai direto no corpo da chamada e não é registrado em lugar nenhum: nem
 * aqui, nem no log de erro (só o código e a descrição que o Asaas devolve).
 */
class AsaasPaymentGateway implements PaymentGatewayContract
{
    /** A documentação pede no mínimo 60 s: com menos, a chamada pode ser repetida e cobrar em dobro. */
    private const TIMEOUT_SECONDS = 60;

    private const PAID = ['CONFIRMED', 'RECEIVED', 'RECEIVED_IN_CASH', 'DUNNING_RECEIVED'];

    private const OVERDUE = ['OVERDUE', 'DUNNING_REQUESTED'];

    private const REFUNDED = ['REFUNDED', 'REFUND_REQUESTED', 'REFUND_IN_PROGRESS'];

    private const CHARGEBACK = ['CHARGEBACK_REQUESTED', 'CHARGEBACK_DISPUTE', 'AWAITING_CHARGEBACK_REVERSAL'];

    public function __construct(
        private string $apiKey,
        private string $baseUrl,
    ) {}

    public function saveCustomer(BillingAccount $account): string
    {
        $body = [
            'name' => $account->name,
            'cpfCnpj' => $account->tax_id,
            'email' => $account->email,
            'mobilePhone' => $account->phone,
            'postalCode' => $account->postal_code,
            'addressNumber' => $account->address_number,
            'complement' => $account->address_complement,
            'externalReference' => $account->tenant_id,
        ];

        $response = $account->gateway_customer_id
            ? $this->send(fn (PendingRequest $http) => $http->put("customers/{$account->gateway_customer_id}", $body))
            : $this->send(fn (PendingRequest $http) => $http->post('customers', $body));

        return (string) $response->json('id');
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
        $body = [
            'customer' => $account->gateway_customer_id,
            'billingType' => $billingType,
            'value' => $this->reais($valueCents),
            'nextDueDate' => $firstDueDate->format('Y-m-d'),
            'cycle' => 'MONTHLY',
            'description' => $description,
            'externalReference' => $externalReference,
            ...($card !== null ? $this->cardFields($account, $card, $remoteIp) : []),
        ];

        return $this->subscriptionFrom($this->send(fn (PendingRequest $http) => $http->post('subscriptions', $body)));
    }

    public function updateSubscription(string $subscriptionId, int $valueCents, string $description): void
    {
        $this->send(fn (PendingRequest $http) => $http->put("subscriptions/{$subscriptionId}", [
            'value' => $this->reais($valueCents),
            'description' => $description,
            'updatePendingPayments' => true,
        ]));
    }

    public function changeSubscriptionPayment(
        string $subscriptionId,
        BillingAccount $account,
        string $billingType,
        #[\SensitiveParameter] ?CardData $card,
        ?string $remoteIp,
    ): GatewaySubscription {
        $previous = $this->send(fn (PendingRequest $http) => $http->get("subscriptions/{$subscriptionId}"))->json('billingType');

        if ($previous !== $billingType) {
            $this->send(fn (PendingRequest $http) => $http->put("subscriptions/{$subscriptionId}", [
                'billingType' => $billingType,
                'updatePendingPayments' => true,
            ]));
        }

        if ($card === null) {
            return new GatewaySubscription($subscriptionId);
        }

        try {
            return $this->subscriptionFrom($this->send(fn (PendingRequest $http) => $http->put(
                "subscriptions/{$subscriptionId}/creditCard",
                $this->cardFields($account, $card, $remoteIp),
            )));
        } catch (PaymentGatewayException $e) {
            // Cartão recusado: a assinatura volta para a forma anterior em vez de ficar no cartão sem cartão.
            if ($previous !== $billingType) {
                $this->send(fn (PendingRequest $http) => $http->put("subscriptions/{$subscriptionId}", [
                    'billingType' => $previous,
                    'updatePendingPayments' => true,
                ]));
            }

            throw $e;
        }
    }

    public function cancelSubscription(string $subscriptionId): void
    {
        $this->send(fn (PendingRequest $http) => $http->delete("subscriptions/{$subscriptionId}"));
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
        $byCard = $billingType === 'CREDIT_CARD' && $cardToken !== null;

        $response = $this->send(fn (PendingRequest $http) => $http->post('payments', array_filter([
            'customer' => $account->gateway_customer_id,
            // Cartão sem token não tem como ser cobrado por nós: vira fatura e o cliente escolhe como pagar.
            'billingType' => $byCard ? 'CREDIT_CARD' : ($billingType === 'CREDIT_CARD' ? 'UNDEFINED' : $billingType),
            'value' => $this->reais($valueCents),
            'dueDate' => $dueDate->format('Y-m-d'),
            'description' => $description,
            'externalReference' => $externalReference,
            'creditCardToken' => $byCard ? $cardToken : null,
        ], fn ($v) => $v !== null)));

        return $this->parsePayment($response->json());
    }

    public function pixQrCode(string $paymentId): PixQrCode
    {
        $data = $this->send(fn (PendingRequest $http) => $http->get("payments/{$paymentId}/pixQrCode"))->json();

        return new PixQrCode(
            image: (string) ($data['encodedImage'] ?? ''),
            payload: (string) ($data['payload'] ?? ''),
            expiresAt: $data['expirationDate'] ?? null,
        );
    }

    public function parsePayment(array $payload): GatewayPayment
    {
        $status = (string) ($payload['status'] ?? 'PENDING');

        return new GatewayPayment(
            id: (string) $payload['id'],
            customerId: (string) ($payload['customer'] ?? ''),
            subscriptionId: $payload['subscription'] ?? null,
            externalReference: $payload['externalReference'] ?? null,
            billingType: (string) ($payload['billingType'] ?? 'UNDEFINED'),
            status: ($payload['deleted'] ?? false) ? BillingPayment::STATUS_CANCELLED : match (true) {
                in_array($status, self::PAID, true) => BillingPayment::STATUS_PAID,
                in_array($status, self::OVERDUE, true) => BillingPayment::STATUS_OVERDUE,
                in_array($status, self::REFUNDED, true) => BillingPayment::STATUS_REFUNDED,
                in_array($status, self::CHARGEBACK, true) => BillingPayment::STATUS_CHARGEBACK,
                default => BillingPayment::STATUS_PENDING,
            },
            valueCents: (int) round(((float) ($payload['value'] ?? 0)) * 100),
            dueDate: (string) $payload['dueDate'],
            paidAt: $payload['clientPaymentDate'] ?? $payload['paymentDate'] ?? $payload['confirmedDate'] ?? null,
            invoiceUrl: $payload['invoiceUrl'] ?? null,
            description: $payload['description'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    private function cardFields(BillingAccount $account, #[\SensitiveParameter] CardData $card, ?string $remoteIp): array
    {
        return [
            'creditCard' => [
                'holderName' => $card->holderName,
                'number' => $card->number,
                'expiryMonth' => $card->expiryMonth,
                'expiryYear' => $card->expiryYear,
                'ccv' => $card->cvv,
            ],
            'creditCardHolderInfo' => [
                'name' => $account->name,
                'email' => $account->email,
                'cpfCnpj' => $account->tax_id,
                'postalCode' => $account->postal_code,
                'addressNumber' => $account->address_number,
                'addressComplement' => $account->address_complement,
                'mobilePhone' => $account->phone,
            ],
            // IP de quem digitou o cartão (antifraude do Asaas), nunca o do servidor.
            'remoteIp' => $remoteIp,
        ];
    }

    private function subscriptionFrom(Response $response): GatewaySubscription
    {
        return new GatewaySubscription(
            id: (string) $response->json('id'),
            cardBrand: $response->json('creditCard.creditCardBrand'),
            cardLast4: $response->json('creditCard.creditCardNumber'),
            cardToken: $response->json('creditCard.creditCardToken'),
        );
    }

    /** @param  callable(PendingRequest): Response  $call */
    private function send(callable $call): Response
    {
        try {
            $response = $call(Http::baseUrl(rtrim($this->baseUrl, '/').'/')
                ->withHeaders(['access_token' => $this->apiKey, 'User-Agent' => 'Moduhub/1.0'])
                ->acceptJson()
                ->asJson()
                ->timeout(self::TIMEOUT_SECONDS));
        } catch (ConnectionException) {
            throw new PaymentGatewayException('Não conseguimos falar com o Asaas agora. Tente de novo em alguns minutos.');
        }

        if ($response->successful()) {
            return $response;
        }

        $error = $response->json('errors.0') ?? [];
        Log::warning('Asaas recusou a chamada.', ['status' => $response->status(), 'code' => $error['code'] ?? null, 'description' => $error['description'] ?? null]);

        // 401/403/5xx são problema nosso ou do Asaas, não do cliente: mensagem genérica.
        if ($response->status() !== 400 || ! isset($error['description'])) {
            throw new PaymentGatewayException('O processador de pagamentos não aceitou a operação. Tente de novo em alguns minutos.', $error['code'] ?? null);
        }

        throw new PaymentGatewayException((string) $error['description'], $error['code'] ?? null);
    }

    private function reais(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
