<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

/**
 * Leva o tenant de quem despachou o job até o worker. O payload ganha o tenant_id do contexto, e o
 * worker o restaura no JobProcessing, que roda antes de o job ser desserializado: assim os models
 * serializados (e as relações que o Laravel recarrega com eles) já passam pelo TenantScope certo.
 * O worker zera o CurrentTenant (scoped) no início de cada job, então nada sobra de um job para outro.
 */
final class QueueTenancy
{
    public static function register(): void
    {
        Queue::createPayloadUsing(static function (): array {
            $tenantId = app(CurrentTenant::class)->id();

            return $tenantId === null ? [] : ['tenant_id' => $tenantId];
        });

        Event::listen(JobProcessing::class, static function (JobProcessing $event): void {
            $tenantId = $event->job->payload()['tenant_id'] ?? null;
            if ($tenantId === null) {
                return;
            }

            $tenant = Tenant::query()->find($tenantId);
            if ($tenant !== null) {
                app(CurrentTenant::class)->set($tenant);
            }
        });
    }
}
