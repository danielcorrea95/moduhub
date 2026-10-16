<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Module extends Model
{
    public const SIGNATURES = 'signatures';
    public const INVOICES = 'invoices';

    /** Módulos com telas prontas. Os demais aparecem como "em breve" no site e no painel. */
    public const RELEASED = [self::SIGNATURES];

    public function isReleased(): bool
    {
        return in_array($this->key, self::RELEASED, true);
    }

    protected $fillable = [
        'key',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_module')
            ->withPivot(['included_quota', 'overage_cents'])
            ->withTimestamps();
    }
}
