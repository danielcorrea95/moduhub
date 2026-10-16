<?php

namespace App\Platform\Contracts;

use App\Models\Plan;
use Illuminate\Support\Collection;

interface PlanRepositoryContract
{
    /**
     * Planos à venda (ativos e públicos), do mais barato ao mais caro, com os módulos e cotas.
     *
     * @return Collection<int, Plan>
     */
    public function publicCatalog(): Collection;
}
