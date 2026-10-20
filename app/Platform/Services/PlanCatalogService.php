<?php

namespace App\Platform\Services;

use App\Models\Module;
use App\Models\Plan;
use App\Platform\Contracts\PlanRepositoryContract;

/**
 * O catálogo à venda agrupado por produto, como aparece na página de vendas e no carrinho da
 * assinatura. Cada plano pertence a um produto (um módulo); plano com vários módulos (combo antigo)
 * fica fora do carrinho.
 */
class PlanCatalogService
{
    /** Ordem dos produtos na página. */
    private const ORDER = [Module::SIGNATURES, Module::INVOICES, Module::MAILING];

    public function __construct(
        private PlanRepositoryContract $plans,
    ) {}

    /** @return list<array<string, mixed>> */
    public function products(): array
    {
        return $this->plans->publicCatalog()
            ->filter(fn (Plan $plan) => $plan->modules->count() === 1)
            ->groupBy(fn (Plan $plan) => $plan->product()->key)
            ->sortBy(fn ($plans, string $key) => array_search($key, self::ORDER, true) === false ? 99 : array_search($key, self::ORDER, true))
            ->map(function ($plans) {
                $module = $plans->first()->product();

                return [
                    'key' => $module->key,
                    'name' => $module->name,
                    'description' => $module->description,
                    'released' => $module->isReleased(),
                    'plans' => $plans->map(fn (Plan $plan) => self::plan($plan))->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public static function plan(Plan $plan): array
    {
        $terms = $plan->modules->first()?->pivot;

        return [
            'id' => $plan->id,
            'key' => $plan->key,
            'name' => $plan->name,
            'description' => $plan->description,
            'price_cents' => (int) $plan->price_cents,
            'trial_days' => (int) $plan->trial_days,
            'product' => $plan->product()?->key,
            'included_quota' => $terms?->included_quota,
            'daily_quota' => $terms?->daily_quota,
            'overage_cents' => $terms?->overage_cents,
        ];
    }
}
