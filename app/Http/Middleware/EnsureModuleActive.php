<?php

namespace App\Http\Middleware;

use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleActive
{
    public function __construct(protected CurrentTenant $currentTenant) {}

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
