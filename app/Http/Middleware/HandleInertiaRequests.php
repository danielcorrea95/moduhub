<?php

namespace App\Http\Middleware;

use App\Platform\Services\PlanUsageService;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
use Inertia\Middleware;

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
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
