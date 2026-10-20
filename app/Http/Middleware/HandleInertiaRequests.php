<?php

namespace App\Http\Middleware;

use App\Modules\Billing\Services\BillingOverviewService;
use App\Platform\Services\PlanUsageService;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $currentTenant = app(CurrentTenant::class)->get();

        $workspaces = [];
        $role = null;

        if ($user !== null) {
            $workspaces = $user->tenants()
                ->orderBy('tenants.name')
                ->get()
                ->map(fn ($t) => [
                    'id' => $t->getKey(),
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'role' => $t->pivot->role,
                ])->all();

            if ($currentTenant !== null) {
                $role = $user->tenantRole($currentTenant);
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'currentTenant' => $currentTenant ? [
                'id' => $currentTenant->getKey(),
                'name' => $currentTenant->name,
                'slug' => $currentTenant->slug,
                'branding' => $currentTenant->branding,
                'role' => $role,
                'modules' => $currentTenant->activeModules()->pluck('key'),
            ] : null,
            'workspaces' => $workspaces,
            'planUsage' => fn () => $currentTenant !== null && $user !== null
                ? app(PlanUsageService::class)->forTenant($currentTenant)
                : [],
            'billingNotice' => fn () => $currentTenant !== null && $user !== null
                ? app(BillingOverviewService::class)->notice($currentTenant, $user)
                : null,
            // Rotas do Ziggy para o route() do SSR. Só na primeira carga (HTML), que é a única que passa pelo
            // servidor de SSR; nas navegações seguintes o navegador já tem o @routes.
            'ziggy' => fn () => $request->header('X-Inertia') ? null : [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
