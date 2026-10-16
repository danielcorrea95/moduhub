<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApiKey\StoreApiKeyRequest;
use App\Models\ApiKey;
use App\Platform\Contracts\ApiKeyRepositoryContract;
use App\Platform\Contracts\ApiKeyServiceContract;
use App\Platform\Contracts\TenantRepositoryContract;
use App\Platform\ScopeCatalog;
use App\Tenancy\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiKeyController extends Controller
{
    public function __construct(
        protected ApiKeyRepositoryContract $repo,
        protected ApiKeyServiceContract $service,
        protected TenantRepositoryContract $tenants,
    ) {}

    public function index(Request $request, CurrentTenant $current): Response
    {
        $tenant = $current->get();
        abort_if($tenant === null, 404);
        $this->authorize('viewAny', ApiKey::class);

        return Inertia::render('ApiKeys/Index', [
            'apiKeys' => $this->repo->listForCurrentTenant(),
            'scopes' => ScopeCatalog::forModules($this->tenants->activeModuleKeys($tenant)),
            'reveal' => $request->session()->pull('api_key_plaintext'),
        ]);
    }

    public function store(StoreApiKeyRequest $request, CurrentTenant $current): RedirectResponse
    {
        $tenant = $current->get();

        $result = $this->service->generate(
            $tenant,
            $request->user(),
            $request->string('name')->toString(),
            $request->input('scopes', []),
            $request->string('environment')->toString(),
            $request->filled('expires_at') ? Carbon::parse($request->string('expires_at')) : null,
        );

        $request->session()->flash('api_key_plaintext', [
            'id' => $result['api_key']->getKey(),
            'name' => $result['api_key']->name,
            'plaintext' => $result['plaintext'],
        ]);
        $request->session()->flash('success', 'API key criada. Copie o segredo agora — ele não será mostrado de novo.');

        return redirect()->route('api-keys.index');
    }

    public function destroy(Request $request, ApiKey $apiKey): RedirectResponse
    {
        $this->authorize('delete', $apiKey);

        $this->service->revoke($apiKey);
        $request->session()->flash('success', 'API key revogada.');

        return back();
    }
}
