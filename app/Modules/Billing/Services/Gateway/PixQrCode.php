<?php

namespace App\Modules\Billing\Services\Gateway;

final readonly class PixQrCode
{
    public function __construct(
        /** PNG em base64. */
        public string $image,
        /** Copia e cola. */
        public string $payload,
        public ?string $expiresAt,
    ) {}
}
