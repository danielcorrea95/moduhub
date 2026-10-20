<?php

namespace App\Tenancy;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\TenantUser;
use App\Models\User;
use App\Platform\Contracts\PlanRepositoryContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantFactory
{
    public function __construct(protected PlanRepositoryContract $plans) {}

    public function createForOwner(User $owner, array $attrs = []): Tenant
    {
        $name = $attrs['name'] ?? ($owner->name ? $owner->name . "'s workspace" : 'My workspace');
        $slug = $this->uniqueSlug($attrs['slug'] ?? $name);

        return DB::transaction(function () use ($owner, $name, $slug, $attrs) {
            $tenant = Tenant::create([
                'name' => $name,
                'slug' => $slug,
                'tax_id' => $attrs['tax_id'] ?? null,
                'status' => 'active',
                'branding' => [
                    'primary_color' => '#4F46E5',
                    'logo_url' => null,
                    'support_email' => null,
                ],
            ]);

            $tenant->users()->attach($owner->getKey(), [
                'role' => TenantUser::ROLE_OWNER,
                'joined_at' => now(),
            ]);

            $this->startTrial($tenant, $attrs['plan_keys'] ?? []);

            return $tenant;
        });
    }

    /**
     * Teste grátis com os planos escolhidos na página de vendas ou, sem escolha, com o plano de entrada
     * (o mais barato) de cada produto à venda: quem testa conhece a plataforma inteira.
     *
     * @param  list<string>  $planKeys
     */
    protected function startTrial(Tenant $tenant, array $planKeys = []): void
    {
        $catalog = $this->plans->publicCatalog()->filter(fn (Plan $plan) => $plan->modules->isNotEmpty()
            && $plan->modules->every(fn ($m) => $m->isReleased()));

        $chosen = $catalog->whereIn('key', $planKeys)->unique(fn (Plan $plan) => $plan->modules->pluck('key')->sort()->join(','));
        $plans = $chosen->isNotEmpty()
            ? $chosen
            : $catalog->groupBy(fn (Plan $plan) => $plan->modules->pluck('key')->sort()->join(','))
                ->map(fn ($group) => $group->sortBy('price_cents')->first())
                ->values();

        $trialDays = (int) ($plans->max('trial_days') ?: 14);

        $subscription = TenantSubscription::create([
            'tenant_id' => $tenant->getKey(),
            'status' => TenantSubscription::STATUS_TRIAL,
            'trial_ends_at' => now()->addDays($trialDays),
            'current_period_start' => now(),
            'current_period_end' => now()->addDays($trialDays),
        ]);

        foreach ($plans as $plan) {
            $subscription->plans()->attach($plan->id, ['tenant_id' => $tenant->getKey(), 'price_cents' => $plan->price_cents]);

            foreach ($plan->modules as $module) {
                $tenant->modules()->syncWithoutDetaching([
                    $module->id => [
                        'source' => 'plan',
                        'activated_at' => now(),
                        'is_active' => true,
                    ],
                ]);
            }
        }
    }

    protected function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base);
        if ($slug === '') {
            $slug = 'workspace';
        }

        $candidate = $slug;
        $i = 1;
        while (Tenant::where('slug', $candidate)->exists()) {
            $i++;
            $candidate = $slug . '-' . $i;
        }

        return $candidate;
    }
}
