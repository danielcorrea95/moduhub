<?php

namespace App\Http\Middleware;

use App\Modules\Billing\Contracts\SubscriptionRepositoryContract;
use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleActive
{
    public function __construct(
        protected CurrentTenant $currentTenant,
        protected SubscriptionRepositoryContract $subscriptions,
    ) {}

    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $tenant = $this->currentTenant->get();

        if ($tenant === null) {
            return response()->json(['message' => 'Tenant context required.'], 400);
        }

        if (! $tenant->hasModule($moduleKey)) {
            return response()->json([
                'message' => "Module [{$moduleKey}] is not active for this tenant.",
                'module' => $moduleKey,
            ], 402);
        }

        // Sem pagamento a API só lê, como o painel.
        if (! $request->isMethodSafe() && $this->subscriptions->current($tenant)?->isBlocked()) {
            return response()->json([
                'message' => 'Workspace blocked for non-payment. Read-only until the subscription is settled.',
                'code' => 'subscription_blocked',
            ], 402);
        }

        $apiKey = $request->attributes->get('api_key');
        // Basta algum escopo do módulo; a exigência fina (read/write) fica no middleware api.scope de cada rota.
        if ($apiKey !== null && ! $apiKey->hasScope($moduleKey . ':read') && ! $apiKey->hasScope($moduleKey . ':write')) {
            return response()->json([
                'message' => "API key lacks scope for module [{$moduleKey}].",
                'module' => $moduleKey,
            ], 403);
        }

        return $next($request);
    }
}
