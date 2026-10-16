<?php

namespace App\Tenancy;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantFactory
{
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

            $this->startTrial($tenant);

            return $tenant;
        });
    }

    protected function startTrial(Tenant $tenant): void
    {
        $plan = Plan::where('key', 'starter')->first();
        $trialDays = $plan?->trial_days ?? 14;

        TenantSubscription::create([
            'tenant_id' => $tenant->getKey(),
            'plan_id' => $plan?->id,
            'status' => TenantSubscription::STATUS_TRIAL,
            'trial_ends_at' => now()->addDays($trialDays),
            'current_period_start' => now(),
            'current_period_end' => now()->addDays($trialDays),
        ]);

        if ($plan !== null) {
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
