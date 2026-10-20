<?php

namespace App\Modules\Billing\Repositories;

use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Modules\Billing\Contracts\SubscriptionRepositoryContract;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class SubscriptionRepository implements SubscriptionRepositoryContract
{
    public function current(Tenant $tenant): ?TenantSubscription
    {
        // Filtra pelo tenant recebido, então funciona também fora de contexto (webhook, comando). O
        // latestOfMany da relação aplicaria o scope na subconsulta e exigiria contexto.
        return TenantSubscription::query()
            ->withoutTenantScope()
            ->where('tenant_id', $tenant->getKey())
            ->orderByDesc('id')
            ->with('plans.modules')
            ->first();
    }

    public function needingAttention(CarbonInterface $now): Collection
    {
        // Comando agendado, sem tenant: varre todos os workspaces e só a assinatura vigente de cada um.
        $latest = TenantSubscription::query()
            ->withoutTenantScope()
            ->selectRaw('MAX(id)')
            ->groupBy('tenant_id');

        return TenantSubscription::query()
            ->withoutTenantScope()
            ->whereIn('id', $latest)
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('status', TenantSubscription::STATUS_TRIAL)->where('trial_ends_at', '<=', $now))
                ->orWhere('status', TenantSubscription::STATUS_PAST_DUE)
                ->orWhere(fn ($q) => $q->whereNotNull('gateway_subscription_id')->where('current_period_end', '<=', $now)
                    ->whereIn('status', [
                        TenantSubscription::STATUS_ACTIVE,
                        TenantSubscription::STATUS_PAST_DUE,
                        TenantSubscription::STATUS_SUSPENDED,
                    ]))
                // Cancelada só até o fechamento do último ciclo, que preenche suspended_at.
                ->orWhere(fn ($q) => $q->whereNotNull('gateway_subscription_id')->where('current_period_end', '<=', $now)
                    ->where('status', TenantSubscription::STATUS_CANCELLED)
                    ->whereNull('suspended_at')))
            ->with(['tenant', 'plans.modules'])
            ->get();
    }
}
