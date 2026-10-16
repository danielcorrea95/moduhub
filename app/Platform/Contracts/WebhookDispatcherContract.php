<?php

namespace App\Platform\Contracts;

use App\Models\Tenant;

interface WebhookDispatcherContract
{
    public function fire(Tenant $tenant, string $event, array $payload): void;
}
