<?php

namespace App\Platform\Usage;

/** Uma linha da cobrança de excedente, como "12 notas excedentes". */
final readonly class BillableLine
{
    public function __construct(
        public string $label,
        public int $quantity,
        public int $unitCents,
    ) {}

    public function totalCents(): int
    {
        return $this->quantity * $this->unitCents;
    }

    /** @return array{label: string, quantity: int, unit_cents: int, cents: int} */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'quantity' => $this->quantity,
            'unit_cents' => $this->unitCents,
            'cents' => $this->totalCents(),
        ];
    }
}
