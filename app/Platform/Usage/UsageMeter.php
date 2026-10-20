<?php

namespace App\Platform\Usage;

use Carbon\CarbonInterface;

/**
 * Quanto o workspace já usou de um módulo no ciclo. Limite nulo = sem limite.
 */
final readonly class UsageMeter
{
    public function __construct(
        public string $key,
        public string $label,
        public int $used,
        public ?int $limit,
        public CarbonInterface $resetsAt,
        /** Linha extra para o tooltip, como o uso de hoje no e-mail. */
        public ?string $detail = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'used' => $this->used,
            'limit' => $this->limit,
            'resets_at' => $this->resetsAt->toIso8601String(),
            'detail' => $this->detail,
        ];
    }
}
