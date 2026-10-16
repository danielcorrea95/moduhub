<?php

namespace App\Http\Controllers;

use App\Http\Requests\Webhook\StoreWebhookRequest;
use App\Http\Requests\Webhook\UpdateWebhookRequest;
use App\Models\WebhookEndpoint;
use App\Platform\Contracts\TenantRepositoryContract;
use App\Platform\Contracts\WebhookRepositoryContract;
use App\Platform\Contracts\WebhookServiceContract;
use App\Platform\EventCatalog;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebhookEndpointController extends Controller
{
    public function __construct(
        protected WebhookRepositoryContract $repo,
        protected WebhookServiceContract $service,
        protected TenantRepositoryContract $tenants,
    ) {}

    public function index(Request $request, CurrentTenant $current): Response
    {
        $tenant = $current->get();
        abort_if($tenant === null, 404);
        $this->authorize('viewAny', WebhookEndpoint::class);

        return Inertia::render('Webhooks/Index', [
            'endpoints' => $this->repo->listForCurrentTenant(),
            'eventsByModule' => EventCatalog::forModules($this->tenants->activeModuleKeys($tenant)),
            'reveal' => $request->session()->pull('webhook_secret'),
        ]);
    }

    public function show(WebhookEndpoint $endpoint): Response
    {
        $this->authorize('view', $endpoint);

        return Inertia::render('Webhooks/Show', [
            'endpoint' => [
                'id' => $endpoint->getKey(),
                'url' => $endpoint->url,
                'events' => $endpoint->events,
                'is_active' => $endpoint->is_active,
                'last_success_at' => $endpoint->last_success_at,
                'last_failure_at' => $endpoint->last_failure_at,
                'consecutive_failures' => $endpoint->consecutive_failures,
            ],
            'deliveries' => $this->repo->recentDeliveries($endpoint),
        ]);
    }

    public function store(StoreWebhookRequest $request, CurrentTenant $current): RedirectResponse
    {
        $result = $this->service->createEndpoint($current->get(), $request->validated());

        $request->session()->flash('webhook_secret', [
            'id' => $result['endpoint']->getKey(),
            'url' => $result['endpoint']->url,
            'secret' => $result['secret'],
        ]);
        $request->session()->flash('success', 'Webhook criado. Copie o secret agora — ele será usado para validar as assinaturas.');

        return redirect()->route('webhooks.index');
    }

    public function update(UpdateWebhookRequest $request, WebhookEndpoint $endpoint): RedirectResponse
    {
        $this->service->updateEndpoint($endpoint, $request->validated());
        $request->session()->flash('success', 'Webhook atualizado.');

        return back();
    }

    public function destroy(Request $request, WebhookEndpoint $endpoint): RedirectResponse
    {
        $this->authorize('delete', $endpoint);

        $this->service->deleteEndpoint($endpoint);
        $request->session()->flash('success', 'Webhook removido.');

        return redirect()->route('webhooks.index');
    }

    public function ping(Request $request, WebhookEndpoint $endpoint): RedirectResponse
    {
        $this->authorize('update', $endpoint);

        $this->service->sendTestPing($endpoint, $request->user());
        $request->session()->flash('success', 'Ping enviado. Acompanhe o resultado abaixo.');

        return back();
    }
}
