<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasUlids, BelongsToTenant;

    public const ENV_LIVE = 'live';
    public const ENV_TEST = 'test';

    protected $fillable = [
        'tenant_id',
        'name',
        'prefix',
        'hash',
        'last_four',
        'scopes',
        'environment',
        'last_used_at',
        'expires_at',
        'revoked_at',
        'created_by',
    ];

    protected $hidden = ['hash'];

    protected $casts = [
        'scopes' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }
        return true;
    }

    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [];

        if (in_array('*', $scopes, true)) {
            return true;
        }

        foreach ($scopes as $granted) {
            if ($granted === $scope) {
                return true;
            }
            if (str_ends_with($granted, ':*')) {
                $prefix = substr($granted, 0, -1);
                if (str_starts_with($scope, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function generatePlaintext(string $environment = self::ENV_LIVE): string
    {
        $prefix = $environment === self::ENV_TEST ? 'mdhb_test_' : 'mdhb_live_';
        return $prefix . Str::random(40);
    }
}
