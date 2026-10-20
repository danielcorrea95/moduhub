<?php

namespace App\Modules\Billing\Services\Gateway;

final readonly class GatewaySubscription
{
    public function __construct(
        public string $id,
        public ?string $cardBrand = null,
        public ?string $cardLast4 = null,
        /** Nulo sem a tokenização habilitada na conta do Asaas. */
        public ?string $cardToken = null,
    ) {}
}
