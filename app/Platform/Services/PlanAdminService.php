<?php

namespace App\Platform\Services;

use App\Models\Module;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cadastro dos planos pelo admin da plataforma. Cada plano é de um produto (módulo).
 * Preço novo vale só para assinaturas novas (o item guarda o preço contratado); cota e excedente
 * valem na hora para todos, porque são lidos do plano.
 */
class PlanAdminService
{
    /** @param  array<string, mixed>  $data */
    public function create(array $data): Plan
    {
        return DB::transaction(function () use ($data) {
            $plan = Plan::create([
                ...$this->planAttributes($data),
                'key' => $this->uniqueKey($data['key'] ?? null, $data['product'], $data['name']),
                'currency' => 'BRL',
                'billing_period' => 'monthly',
            ]);

            $plan->modules()->sync([$this->moduleId($data['product']) => $this->terms($data)]);

            return $plan->load('modules');
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(Plan $plan, array $data): Plan
    {
        $moduleId = $this->moduleId($data['product']);
        $plan->loadCount('subscriptionItems')->load('modules');

        if ($plan->product()?->id !== $moduleId && $plan->subscription_items_count > 0) {
            throw ValidationException::withMessages(['product' => 'Este plano já tem assinantes; o produto não pode mudar. Crie um plano novo.']);
        }

        return DB::transaction(function () use ($plan, $data, $moduleId) {
            $plan->update($this->planAttributes($data));
            $plan->modules()->sync([$moduleId => $this->terms($data)]);

            return $plan->load('modules');
        });
    }

    /** @return array<string, mixed> */
    private function planAttributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price_cents' => (int) $data['price_cents'],
            'trial_days' => (int) $data['trial_days'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) $data['is_active'],
            'is_public' => (bool) $data['is_public'],
        ];
    }

    /** @return array<string, int|null> */
    private function terms(array $data): array
    {
        return [
            'included_quota' => $data['included_quota'] ?? null,
            // Limite por dia só existe no disparo de e-mail.
            'daily_quota' => $data['product'] === Module::MAILING ? ($data['daily_quota'] ?? null) : null,
            'overage_cents' => $data['overage_cents'] ?? null,
        ];
    }

    private function moduleId(string $key): int
    {
        return (int) Module::query()->where('key', $key)->value('id');
    }

    private function uniqueKey(?string $key, string $product, string $name): string
    {
        $base = Str::slug($key ?: "{$product}-{$name}");
        $candidate = $base;
        for ($i = 2; Plan::query()->where('key', $candidate)->exists(); $i++) {
            $candidate = "{$base}-{$i}";
        }

        return $candidate;
    }
}
