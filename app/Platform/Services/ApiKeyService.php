<?php

namespace App\Platform\Services;

use App\Models\ApiKey;
use App\Models\Tenant;
use App\Models\User;
use App\Platform\Contracts\ApiKeyServiceContract;
use App\Platform\ScopeCatalog;
use Illuminate\Support\Str;

class ApiKeyService implements ApiKeyServiceContract
{
    /**
     * @return array{api_key: ApiKey, plaintext: string}
     */
    public function generate(
        Tenant $tenant,
        User $actor,
        string $name,
        array $scopes,
        string $environment = ApiKey::ENV_LIVE,
        ?\DateTimeInterface $expiresAt = null,
    ): array {
        $plaintext = $this->buildPlaintext($environment);
        $prefix = $this->prefix($environment);
        $hash = hash('sha256', $plaintext);
        $lastFour = substr($plaintext, -4);

        $apiKey = ApiKey::create([
            'tenant_id' => $tenant->getKey(),
            'name' => $name,
            'prefix' => $prefix,
            'hash' => $hash,
            'last_four' => $lastFour,
            'scopes' => $this->normalizeScopes($scopes),
            'environment' => $environment,
            'expires_at' => $expiresAt,
            'created_by' => $actor->getKey(),
        ]);

        return [
            'api_key' => $apiKey,
            'plaintext' => $plaintext,
        ];
    }

    public function revoke(ApiKey $apiKey): void
    {
        $apiKey->forceFill(['revoked_at' => now()])->save();
    }

    protected function buildPlaintext(string $environment): string
    {
        return $this->prefix($environment) . Str::random(40);
    }

    protected function prefix(string $environment): string
    {
        return $environment === ApiKey::ENV_TEST ? 'mdhb_test_' : 'mdhb_live_';
    }

    protected function normalizeScopes(array $scopes): array
    {
        $allowed = ScopeCatalog::keys();
        $filtered = array_values(array_unique(array_filter(
            $scopes,
            fn ($s) => in_array($s, $allowed, true),
        )));

        if (count($filtered) === 0) {
            $filtered = [ScopeCatalog::WILDCARD];
        }

        return $filtered;
    }
}
