<?php

namespace App\Modules\Invoices\Services\Adn;

use Carbon\CarbonImmutable;

/** Um item do lote da distribuição (DistribuicaoNSU), com o XML já descompactado. */
final readonly class DistributedDfe
{
    public function __construct(
        public int $nsu,
        public string $accessKey,
        /** NFSE, EVENTO, DPS, PEDIDO_REGISTRO_EVENTO ou CNC */
        public string $type,
        /** CANCELAMENTO, REJEICAO_TOMADOR etc., só para eventos */
        public ?string $eventType,
        public string $xml,
        public ?CarbonImmutable $generatedAt,
    ) {}
}
