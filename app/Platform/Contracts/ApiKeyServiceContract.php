<?php

namespace App\Platform\Contracts;

use App\Models\ApiKey;
use App\Models\Tenant;
use App\Models\User;
use DateTimeInterface;

interface ApiKeyServiceContract
{
    /**
     * @return array{api_key: ApiKey, plaintext: string}
     */
    public function generate(
        Tenant $tenant,
        User $actor,
        string $name,
        array $scopes,
        string $environment = 'live',
        ?DateTimeInterface $expiresAt = null,
    ): array;

    public function revoke(ApiKey $apiKey): void;
}
