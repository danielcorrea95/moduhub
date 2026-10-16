<?php

namespace App\Platform\Repositories;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Platform\Contracts\WebhookRepositoryContract;
use Illuminate\Support\Collection;

class WebhookRepository implements WebhookRepositoryContract
{
    public function listForCurrentTenant(): Collection
    {
        return WebhookEndpoint::query()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (WebhookEndpoint $e) => [
                'id' => $e->getKey(),
                'url' => $e->url,
                'events' => $e->events,
                'is_active' => $e->is_active,
                'last_success_at' => $e->last_success_at,
                'last_failure_at' => $e->last_failure_at,
                'consecutive_failures' => $e->consecutive_failures,
            ]);
    }

    public function recentDeliveries(WebhookEndpoint $endpoint, int $limit = 50): Collection
    {
        return WebhookDelivery::query()
            ->where('webhook_endpoint_id', $endpoint->getKey())
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (WebhookDelivery $d) => [
                'id' => $d->getKey(),
                'event' => $d->event,
                'status' => $d->status,
                'attempt' => $d->attempt,
                'response_status' => $d->response_status,
                'response_body' => $d->response_body,
                'next_attempt_at' => $d->next_attempt_at,
                'delivered_at' => $d->delivered_at,
                'created_at' => $d->created_at,
            ]);
    }
}
