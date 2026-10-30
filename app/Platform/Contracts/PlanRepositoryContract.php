<?php

namespace App\Platform\Contracts;

use App\Models\Plan;
use Illuminate\Support\Collection;

interface PlanRepositoryContract
{
    /**
     * Planos à venda (ativos e públicos), na ordem do admin e do mais barato ao mais caro, com os
     * módulos e cotas.
     *
     * @return Collection<int, Plan>
     */
    public function publicCatalog(): Collection;

    /**
     * Planos à venda com esses ids. Os que não estão à venda ficam de fora.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Plan>
     */
    public function findManyForSale(array $ids): Collection;

    /**
     * Idem, pela chave (a da URL da página de vendas).
     *
     * @param  list<string>  $keys
     * @return Collection<int, Plan>
     */
    public function findManyForSaleByKeys(array $keys): Collection;

    /**
     * Planos ativos, à venda ou ocultos do site, com os módulos. Para a cortesia dada pelo admin.
     *
     * @param  list<int>|null  $ids  nulo traz todos
     * @return Collection<int, Plan>
     */
    public function active(?array $ids = null): Collection;

    /**
     * Todos os planos, ativos ou não, com os módulos e quantas assinaturas usam cada um. Para o admin.
     *
     * @return Collection<int, Plan>
     */
    public function allForAdmin(): Collection;
}
