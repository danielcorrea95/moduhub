<?php

namespace App\Modules\Invoices\Services\Adn;

final readonly class DfeBatch
{
    /** @param  list<DistributedDfe>  $documents */
    public function __construct(
        public array $documents,
        /** O ADN disse que não há mais nada depois do NSU pedido (E2220). */
        public bool $exhausted,
        public ?string $error = null,
    ) {}

    public static function failed(string $error): self
    {
        return new self([], false, $error);
    }
}
