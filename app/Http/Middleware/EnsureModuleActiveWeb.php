<?php

namespace App\Http\Middleware;

use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleActiveWeb
{
    public function __construct(protected CurrentTenant $currentTenant) {}

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

        return $next($request);
    }
}
