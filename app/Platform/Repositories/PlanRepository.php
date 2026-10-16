<?php

namespace App\Platform\Repositories;

use App\Models\Plan;
use App\Platform\Contracts\PlanRepositoryContract;
use Illuminate\Support\Collection;

class PlanRepository implements PlanRepositoryContract
{
    public function publicCatalog(): Collection
    {
        return Plan::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->with('modules')
            ->orderBy('price_cents')
            ->get();
    }
}
