<?php

namespace App\Platform\Repositories;

use App\Models\Plan;
use App\Platform\Contracts\PlanRepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PlanRepository implements PlanRepositoryContract
{
    public function publicCatalog(): Collection
    {
        return $this->forSale()->get();
    }

    public function findManyForSale(array $ids): Collection
    {
        return $this->forSale()->whereIn('id', $ids)->get();
    }

    public function findManyForSaleByKeys(array $keys): Collection
    {
        return $this->forSale()->whereIn('key', $keys)->get();
    }

    public function active(?array $ids = null): Collection
    {
        return Plan::query()
            ->where('is_active', true)
            ->when($ids !== null, fn (Builder $q) => $q->whereIn('id', $ids))
            ->with('modules')
            ->orderBy('sort_order')
            ->orderBy('price_cents')
            ->get();
    }

    public function allForAdmin(): Collection
    {
        return Plan::query()
            ->with('modules')
            ->withCount('subscriptionItems')
            ->orderBy('sort_order')
            ->orderBy('price_cents')
            ->get();
    }

    /** @return Builder<Plan> */
    private function forSale(): Builder
    {
        return Plan::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->with('modules')
            ->orderBy('sort_order')
            ->orderBy('price_cents');
    }
}
