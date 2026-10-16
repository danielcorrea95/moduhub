<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentTenantFromSession
{
    public function __construct(protected CurrentTenant $currentTenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $tenantId = $request->session()->get('current_tenant_id');

        if ($tenantId === null) {
            $tenant = $user->tenants()->first();
            if ($tenant !== null) {
                $tenantId = $tenant->getKey();
                $request->session()->put('current_tenant_id', $tenantId);
            }
        }

        if ($tenantId !== null) {
            $tenant = Tenant::find($tenantId);

            if ($tenant !== null && $user->belongsToTenant($tenant)) {
                $this->currentTenant->set($tenant);
            } else {
                $request->session()->forget('current_tenant_id');
            }
        }

        return $next($request);
    }
}
