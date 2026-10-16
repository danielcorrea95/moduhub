<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEndpoint extends Model
{
    use HasUlids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'url',
        'secret',
        'events',
        'is_active',
        'last_success_at',
        'last_failure_at',
        'consecutive_failures',
    ];

    protected $hidden = ['secret'];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
        'last_success_at' => 'datetime',
        'last_failure_at' => 'datetime',
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function subscribesTo(string $event): bool
    {
        $events = $this->events ?? [];

        if (in_array('*', $events, true)) {
            return true;
        }

        foreach ($events as $pattern) {
            if ($pattern === $event) {
                return true;
            }
            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -1);
                if (str_starts_with($event, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }
}
