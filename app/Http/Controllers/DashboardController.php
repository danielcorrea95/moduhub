<?php

namespace App\Http\Controllers;

use App\Platform\Services\DashboardService;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboard,
    ) {}

    public function __invoke(Request $request, CurrentTenant $current): Response
    {
        $tenant = $current->get();

        // Sem workspace a página mostra só o convite para criar um.
        return Inertia::render('Dashboard', $tenant === null
            ? ['sections' => [], 'alerts' => [], 'billing' => null]
            : $this->dashboard->forTenant($tenant, $request->user()));
    }
}
