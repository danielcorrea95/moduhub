<?php

namespace App\Modules\Billing\Services\Gateway;

/** Cobrança como o Asaas devolve na API e no webhook, já no formato daqui (centavos, status local). */
final readonly class GatewayPayment
{
    public function __construct(
        public string $id,
        public string $customerId,
        public ?string $subscriptionId,
        public ?string $externalReference,
        public string $billingType,
        public string $status,
        public int $valueCents,
        public string $dueDate,
        public ?string $paidAt,
        public ?string $invoiceUrl,
        public ?string $description,
    ) {}
}
