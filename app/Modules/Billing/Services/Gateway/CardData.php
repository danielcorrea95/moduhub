<?php

namespace App\Modules\Billing\Services\Gateway;

/**
 * Cartão digitado no formulário, a caminho do Asaas. Só existe na memória do request: nunca vai
 * para banco, log, fila ou sessão.
 */
final readonly class CardData
{
    public function __construct(
        public string $holderName,
        #[\SensitiveParameter] public string $number,
        public string $expiryMonth,
        public string $expiryYear,
        #[\SensitiveParameter] public string $cvv,
    ) {}

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['holderName' => $this->holderName, 'number' => '****'.substr($this->number, -4)];
    }
}
