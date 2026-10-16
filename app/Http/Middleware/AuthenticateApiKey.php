<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function __construct(protected CurrentTenant $currentTenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $plaintext = $this->extractToken($request);

        if ($plaintext === null || $plaintext === '') {
            return response()->json(['message' => 'API key missing.'], 401);
        }

        $prefix = $this->resolvePrefix($plaintext);
        if ($prefix === null) {
            return response()->json(['message' => 'Malformed API key.'], 401);
        }

        $hash = hash('sha256', $plaintext);

        $apiKey = ApiKey::query()
            ->withoutTenantScope()
            ->where('prefix', $prefix)
            ->where('hash', $hash)
            ->first();

        if ($apiKey === null || ! $apiKey->isActive()) {
            return response()->json(['message' => 'Invalid or revoked API key.'], 401);
        }

        $tenant = $apiKey->tenant()->withoutGlobalScope(\App\Tenancy\TenantScope::class)->first();

        if ($tenant === null || $tenant->status !== 'active') {
            return response()->json(['message' => 'Tenant inactive.'], 403);
        }

        $this->currentTenant->set($tenant);
        $apiKey->forceFill(['last_used_at' => now()])->saveQuietly();

        $request->attributes->set('api_key', $apiKey);
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }

    protected function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if ($header !== null && str_starts_with($header, 'Bearer ')) {
            return trim(substr($header, 7)) ?: null;
        }

        $xApi = $request->header('X-Api-Key');
        return $xApi !== null ? (trim($xApi) ?: null) : null;
    }

    protected function resolvePrefix(string $plaintext): ?string
    {
        if (strlen($plaintext) < 12) {
            return null;
        }
        $pos = strpos($plaintext, '_', 5);
        if ($pos === false) {
            return null;
        }
        return substr($plaintext, 0, $pos + 1);
    }
}
