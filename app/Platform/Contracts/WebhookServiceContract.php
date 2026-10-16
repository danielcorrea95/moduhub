<?php

namespace App\Platform\Contracts;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;

interface WebhookServiceContract
{
    /**
     * @return array{endpoint: WebhookEndpoint, secret: string}
     */
    public function createEndpoint(Tenant $tenant, array $attrs): array;

    public function updateEndpoint(WebhookEndpoint $endpoint, array $attrs): WebhookEndpoint;

    public function deleteEndpoint(WebhookEndpoint $endpoint): void;

    public function sendTestPing(WebhookEndpoint $endpoint, User $actor): WebhookDelivery;
}
