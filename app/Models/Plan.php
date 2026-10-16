<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_public' => 'boolean',
    ];

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'plan_module')
            ->withPivot(['included_quota', 'overage_cents'])
            ->withTimestamps();
    }
}
