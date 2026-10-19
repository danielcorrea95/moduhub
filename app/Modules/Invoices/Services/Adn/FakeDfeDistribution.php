<?php

namespace App\Modules\Invoices\Services\Adn;

use App\Modules\Invoices\Contracts\DfeDistributionContract;
use App\Modules\Invoices\Models\Issuer;

/** Fila do ADN em memória para os testes: devolve em lotes de até 50, como o real. */
class FakeDfeDistribution implements DfeDistributionContract
{
    /** @var list<DistributedDfe> */
    protected array $documents = [];

    protected ?string $nextError = null;

    /** @var list<int> NSUs pedidos, na ordem */
    public array $requests = [];

    public function push(DistributedDfe $document): static
    {
        $this->documents[] = $document;

        return $this;
    }

    public function failNext(string $error = 'ADN indisponível'): static
    {
        $this->nextError = $error;

        return $this;
    }

    public function nextNsu(): int
    {
        return count($this->documents) + 1;
    }

    public function fetch(Issuer $issuer, int $afterNsu): DfeBatch
    {
        $this->requests[] = $afterNsu;

        if ($this->nextError !== null) {
            [$error, $this->nextError] = [$this->nextError, null];

            return DfeBatch::failed($error);
        }

        $pending = array_values(array_filter($this->documents, fn (DistributedDfe $d) => $d->nsu > $afterNsu));

        return $pending === [] ? new DfeBatch([], true) : new DfeBatch(array_slice($pending, 0, 50), false);
    }
}
