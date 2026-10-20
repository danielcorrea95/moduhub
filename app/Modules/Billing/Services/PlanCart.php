<?php

namespace App\Modules\Billing\Services;

use App\Models\Plan;
use App\Platform\Contracts\PlanRepositoryContract;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * O carrinho da assinatura: um plano por produto, todos à venda e de produtos lançados. Os planos
 * voltam na ordem do catálogo.
 */
class PlanCart
{
    public function __construct(
        private PlanRepositoryContract $plans,
    ) {}

    /**
     * @param  list<int>  $planIds
     * @return Collection<int, Plan>
     */
    public function resolve(array $planIds, string $field = 'plan_ids'): Collection
    {
        $plans = $this->plans->findManyForSale(array_values(array_unique(array_map('intval', $planIds))));

        if ($plans->isEmpty()) {
            throw ValidationException::withMessages([$field => 'Escolha pelo menos um plano.']);
        }

        if ($plans->count() !== count(array_unique($planIds))) {
            throw ValidationException::withMessages([$field => 'Um dos planos escolhidos não está mais à venda. Recarregue a página.']);
        }

        $modules = $plans->flatMap(fn (Plan $plan) => $plan->modules);

        if ($modules->contains(fn ($m) => ! $m->isReleased())) {
            throw ValidationException::withMessages([$field => 'Um dos planos escolhidos ainda não está disponível.']);
        }

        if ($modules->pluck('key')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([$field => 'Escolha só um plano de cada produto.']);
        }

        return $plans;
    }

    /** @param  Collection<int, Plan>  $plans */
    public static function total(Collection $plans): int
    {
        return (int) $plans->sum('price_cents');
    }

    /** @param  Collection<int, Plan>  $plans */
    public static function description(Collection $plans): string
    {
        return 'Moduhub - '.$plans->pluck('name')->join(', ', ' e ');
    }
}
