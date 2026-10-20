<?php

namespace App\Modules\Billing\Contracts;

use App\Modules\Billing\Exceptions\PaymentGatewayException;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Services\Gateway\CardData;
use App\Modules\Billing\Services\Gateway\GatewayPayment;
use App\Modules\Billing\Services\Gateway\GatewaySubscription;
use App\Modules\Billing\Services\Gateway\PixQrCode;
use Carbon\CarbonInterface;

/**
 * Provedor de cobrança (Asaas). Valores em centavos; todo método lança PaymentGatewayException
 * quando o provedor recusa ou não responde.
 *
 * @throws PaymentGatewayException
 */
interface PaymentGatewayContract
{
    /** Cria o cliente ou atualiza o existente. Devolve o id do cliente no provedor. */
    public function saveCustomer(BillingAccount $account): string;

    /**
     * Assinatura mensal. Com cartão, o provedor valida o cartão agora e cobra sozinho a cada vencimento;
     * com Pix, gera a cobrança de cada mês e o cliente paga.
     */
    public function createSubscription(
        BillingAccount $account,
        string $billingType,
        int $valueCents,
        CarbonInterface $firstDueDate,
        string $description,
        string $externalReference,
        #[\SensitiveParameter] ?CardData $card,
        ?string $remoteIp,
    ): GatewaySubscription;

    /** Novo valor e descrição (troca de plano), valendo também para as cobranças em aberto. */
    public function updateSubscription(string $subscriptionId, int $valueCents, string $description): void;

    /**
     * Troca a forma de pagamento. Para cartão, o cartão novo é obrigatório e passa a valer também para
     * as cobranças em aberto (sem cobrar na hora).
     */
    public function changeSubscriptionPayment(
        string $subscriptionId,
        BillingAccount $account,
        string $billingType,
        #[\SensitiveParameter] ?CardData $card,
        ?string $remoteIp,
    ): GatewaySubscription;

    /** Encerra a recorrência; o provedor apaga as cobranças em aberto dela. */
    public function cancelSubscription(string $subscriptionId): void;

    /** Cobrança avulsa (excedente). Com token de cartão, cobra no cartão; sem ele, gera fatura. */
    public function createCharge(
        BillingAccount $account,
        string $billingType,
        int $valueCents,
        CarbonInterface $dueDate,
        string $description,
        string $externalReference,
        #[\SensitiveParameter] ?string $cardToken,
    ): GatewayPayment;

    public function pixQrCode(string $paymentId): PixQrCode;

    /**
     * Converte a cobrança do formato do provedor (API ou webhook) para o daqui.
     *
     * @param  array<string, mixed>  $payload
     */
    public function parsePayment(array $payload): GatewayPayment;
}
