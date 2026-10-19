<?php

namespace App\Modules\Invoices\Services;

use Carbon\CarbonInterface;

/** Consumo de notas do workspace no período da assinatura. */
final readonly class InvoiceUsage
{
    public function __construct(
        public int $used,
        /** null = sem cota (sem assinatura ou plano sem limite para o módulo) */
        public ?int $included,
        public ?int $overageCents,
        /** No teste grátis não há cobrança: a cota vira limite. */
        public bool $overageAllowed,
        public CarbonInterface $periodStart,
        public CarbonInterface $periodEnd,
        public ?string $planName,
    ) {}

    public function remaining(): ?int
    {
        return $this->included === null ? null : max(0, $this->included - $this->used);
    }

    public function exhausted(): bool
    {
        return $this->included !== null && $this->used >= $this->included;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'used' => $this->used,
            'included' => $this->included,
            'remaining' => $this->remaining(),
            'overage_allowed' => $this->overageAllowed,
            'overage_cents' => $this->overageCents,
            'period_start' => $this->periodStart->toIso8601String(),
            'period_end' => $this->periodEnd->toIso8601String(),
            'plan' => $this->planName,
        ];
    }
}
