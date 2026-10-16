<?php

namespace App\Platform\Services;

use App\Jobs\DispatchWebhookJob;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Platform\Contracts\WebhookServiceContract;
use Illuminate\Support\Str;

class WebhookService implements WebhookServiceContract
{
    public function createEndpoint(Tenant $tenant, array $attrs): array
    {
        $secret = 'whsec_' . Str::random(40);

        $endpoint = WebhookEndpoint::create([
            'tenant_id' => $tenant->getKey(),
            'url' => $attrs['url'],
            'secret' => $secret,
            'events' => array_values(array_unique($attrs['events'])),
            'is_active' => $attrs['is_active'] ?? true,
        ]);

        return [
            'endpoint' => $endpoint,
            'secret' => $secret,
        ];
    }

    public function updateEndpoint(WebhookEndpoint $endpoint, array $attrs): WebhookEndpoint
    {
        $endpoint->update([
            'url' => $attrs['url'],
            'events' => array_values(array_unique($attrs['events'])),
            'is_active' => $attrs['is_active'] ?? $endpoint->is_active,
        ]);

        return $endpoint->refresh();
    }

    public function deleteEndpoint(WebhookEndpoint $endpoint): void
    {
        $endpoint->delete();
    }

    public function sendTestPing(WebhookEndpoint $endpoint, User $actor): WebhookDelivery
    {
        $delivery = WebhookDelivery::create([
            'tenant_id' => $endpoint->tenant_id,
            'webhook_endpoint_id' => $endpoint->getKey(),
            'event' => 'ping',
            'payload' => [
                'message' => 'Teste disparado manualmente',
                'triggered_by' => $actor->email,
                'triggered_at' => now()->toIso8601String(),
            ],
            'status' => WebhookDelivery::STATUS_PENDING,
        ]);

        DispatchWebhookJob::dispatch($delivery->getKey());

        return $delivery;
    }
}
