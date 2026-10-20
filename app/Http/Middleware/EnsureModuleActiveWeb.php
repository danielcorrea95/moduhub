<?php

namespace App\Http\Middleware;

use App\Modules\Billing\Contracts\SubscriptionRepositoryContract;
use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleActiveWeb
{
    public function __construct(
        protected CurrentTenant $currentTenant,
        protected SubscriptionRepositoryContract $subscriptions,
    ) {}

    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $tenant = $this->currentTenant->get();

        if ($tenant === null) {
            return redirect()->route('workspaces.index');
        }

        if (! $tenant->hasModule($moduleKey)) {
            return Inertia::render('Modules/NotActive', [
                'module' => $moduleKey,
            ])->toResponse($request)->setStatusCode(402);
        }

        // Sem pagamento o workspace só lê: consulta e baixa o que já tem, mas não cria nem altera.
        if (! $request->isMethodSafe() && $this->subscriptions->current($tenant)?->isBlocked()) {
            return back()->with('error', 'O workspace está bloqueado por falta de pagamento. Regularize a assinatura para voltar a criar e editar.');
        }

        return $next($request);
    }
}
