<?php

namespace App\Platform\Services;

use App\Jobs\DispatchWebhookJob;
use App\Models\Tenant;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Platform\Contracts\WebhookDispatcherContract;

class WebhookDispatcher implements WebhookDispatcherContract
{
    public function fire(Tenant $tenant, string $event, array $payload): void
    {
        $endpoints = WebhookEndpoint::query()
            ->withoutTenantScope()
            ->where('tenant_id', $tenant->getKey())
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $e) => $e->subscribesTo($event));

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::create([
                'tenant_id' => $tenant->getKey(),
                'webhook_endpoint_id' => $endpoint->getKey(),
                'event' => $event,
                'payload' => $payload,
                'status' => WebhookDelivery::STATUS_PENDING,
            ]);

            // Dentro de transação, o job só pode rodar depois do commit — antes disso a entrega não existe para ele.
            DispatchWebhookJob::dispatch($delivery->getKey())->afterCommit();
        }
    }
}
