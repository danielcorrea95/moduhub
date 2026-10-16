<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Tenancy\TenantScope;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    protected const BACKOFF_SECONDS = [60, 300, 1800, 7200, 21600, 43200];

    public function __construct(public string $deliveryId) {}

    public function handle(): void
    {
        $delivery = WebhookDelivery::withoutGlobalScope(TenantScope::class)
            ->with(['endpoint' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
            ->find($this->deliveryId);

        if ($delivery === null || $delivery->status === WebhookDelivery::STATUS_SUCCEEDED) {
            return;
        }

        $endpoint = $delivery->endpoint;
        if ($endpoint === null || ! $endpoint->is_active) {
            $delivery->update(['status' => WebhookDelivery::STATUS_FAILED]);
            return;
        }

        $body = [
            'id' => $delivery->getKey(),
            'event' => $delivery->event,
            'created_at' => $delivery->created_at->toIso8601String(),
            'data' => $delivery->payload,
        ];
        $raw = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp . '.' . $raw, $endpoint->secret);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Moduhub-Event' => $delivery->event,
                    'X-Moduhub-Delivery' => $delivery->getKey(),
                    'X-Moduhub-Timestamp' => $timestamp,
                    'X-Moduhub-Signature' => 'sha256=' . $signature,
                ])
                ->withBody($raw, 'application/json')
                ->post($endpoint->url);

            $delivery->forceFill([
                'response_status' => $response->status(),
                'response_body' => mb_substr($response->body(), 0, 10000),
            ]);

            if ($response->successful()) {
                $delivery->status = WebhookDelivery::STATUS_SUCCEEDED;
                $delivery->delivered_at = now();
                $delivery->save();

                $endpoint->forceFill([
                    'last_success_at' => now(),
                    'consecutive_failures' => 0,
                ])->save();
                return;
            }

            $this->handleFailure($delivery, $endpoint);
        } catch (\Throwable $e) {
            $delivery->forceFill([
                'response_status' => null,
                'response_body' => mb_substr($e->getMessage(), 0, 10000),
            ]);
            $this->handleFailure($delivery, $endpoint);
        }
    }

    protected function handleFailure(WebhookDelivery $delivery, $endpoint): void
    {
        $attempt = $delivery->attempt;
        $maxAttempts = count(self::BACKOFF_SECONDS);

        if ($attempt >= $maxAttempts) {
            $delivery->status = WebhookDelivery::STATUS_FAILED;
            $delivery->save();
        } else {
            $delay = self::BACKOFF_SECONDS[$attempt - 1];
            $delivery->attempt = $attempt + 1;
            $delivery->next_attempt_at = now()->addSeconds($delay);
            $delivery->save();

            self::dispatch($delivery->getKey())->delay(now()->addSeconds($delay));
        }

        $endpoint->forceFill([
            'last_failure_at' => now(),
            'consecutive_failures' => ($endpoint->consecutive_failures ?? 0) + 1,
        ])->save();
    }
}
