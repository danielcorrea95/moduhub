<?php

namespace App\Models;

use App\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'price_cents',
        'currency',
        'billing_period',
        'trial_days',
        'is_active',
        'is_public',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'price_cents' => 'integer',
        'trial_days' => 'integer',
        'sort_order' => 'integer',
    ];

    /** O produto do plano. Plano do admin tem um só; os combos antigos podem ter mais. */
    public function product(): ?Module
    {
        return $this->modules->first();
    }

    /** Itens de todos os workspaces: o plano é da plataforma, e o admin conta quem assina. */
    public function subscriptionItems(): HasMany
    {
        return $this->hasMany(TenantSubscriptionItem::class)->withoutGlobalScope(TenantScope::class);
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'plan_module')
            ->withPivot(['included_quota', 'daily_quota', 'overage_cents'])
            ->withTimestamps();
    }
}
