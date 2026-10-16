<?php

namespace App\Http\Controllers;

use App\Http\Requests\Workspace\StoreWorkspaceRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceRequest;
use App\Models\Tenant;
use App\Platform\Contracts\TenantRepositoryContract;
use App\Platform\Contracts\TenantServiceContract;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function __construct(
        protected TenantRepositoryContract $tenants,
        protected TenantServiceContract $service,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Workspaces/Index', [
            'workspaces' => $this->tenants->workspacesForUser($request->user()),
        ]);
    }

    public function store(StoreWorkspaceRequest $request): RedirectResponse
    {
        $tenant = $this->service->create($request->user(), [
            'name' => $request->string('name')->toString(),
        ]);

        $request->session()->put('current_tenant_id', $tenant->getKey());
        $request->session()->flash('success', 'Workspace criado com sucesso.');

        return redirect()->route('dashboard');
    }

    public function switch(Request $request, Tenant $tenant, CurrentTenant $current): RedirectResponse
    {
        abort_unless($request->user()->belongsToTenant($tenant), 403);

        $request->session()->put('current_tenant_id', $tenant->getKey());
        $current->set($tenant);

        return back();
    }

    public function edit(CurrentTenant $current): Response
    {
        $tenant = $current->get();
        abort_if($tenant === null, 404);
        $this->authorize('update', $tenant);

        return Inertia::render('Workspaces/Settings', [
            'tenant' => [
                'id' => $tenant->getKey(),
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'tax_id' => $tenant->tax_id,
                'branding' => $tenant->branding,
            ],
        ]);
    }

    public function update(UpdateWorkspaceRequest $request, CurrentTenant $current): RedirectResponse
    {
        $tenant = $current->get();
        abort_if($tenant === null, 404);

        $this->service->updateProfile($tenant, $request->validated());

        $request->session()->flash('success', 'Workspace atualizado.');

        return back();
    }
}
