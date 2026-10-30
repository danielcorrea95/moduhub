<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EndComplimentaryRequest;
use App\Http\Requests\Admin\GrantComplimentaryRequest;
use App\Http\Requests\Admin\RegisterCustomerRequest;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Modules\Billing\Services\ComplimentaryService;
use App\Platform\Contracts\PlanRepositoryContract;
use App\Platform\Contracts\TenantRepositoryContract;
use App\Platform\Services\CustomerAdminService;
use App\Platform\Services\PlanCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(
        protected TenantRepositoryContract $tenants,
        protected PlanRepositoryContract $plans,
        protected CustomerAdminService $service,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->query('busca');
        $search = is_string($search) ? $search : null;

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $this->tenants->paginateForAdmin($search)->through(fn (Tenant $t) => self::present($t)),
            'search' => $search,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Customers/Create', ['plans' => $this->planOptions()]);
    }

    public function store(RegisterCustomerRequest $request): RedirectResponse
    {
        ['tenant' => $tenant, 'owner_created' => $created] = $this->service->registerComplimentary($request->validated());

        return redirect()->route('admin.customers.edit', $tenant)->with('success', $created
            ? "Workspace {$tenant->name} criado em cortesia. O proprietário recebeu o link para criar a senha."
            : "Workspace {$tenant->name} criado em cortesia na conta que já existia.");
    }

    public function edit(Tenant $tenant): Response
    {
        return Inertia::render('Admin/Customers/Edit', [
            'customer' => self::present($this->tenants->loadForAdmin($tenant)),
            'plans' => $this->planOptions(),
            'endingTrialDays' => ComplimentaryService::ENDING_TRIAL_DAYS,
        ]);
    }

    public function grantComplimentary(GrantComplimentaryRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->service->grantComplimentary($tenant, $request->validated('plan_ids'));

        return back()->with('success', "Cortesia de {$tenant->name} salva.");
    }

    public function endComplimentary(EndComplimentaryRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->service->endComplimentary($tenant);

        $days = ComplimentaryService::ENDING_TRIAL_DAYS;

        return back()->with('success', "Cortesia de {$tenant->name} encerrada. O workspace tem {$days} dias de teste para assinar.");
    }

    /** @return array<string, mixed> */
    private static function present(Tenant $tenant): array
    {
        /** @var TenantSubscription|null $subscription */
        $subscription = $tenant->subscriptions->first();

        return [
            'id' => $tenant->getKey(),
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'tax_id' => $tenant->tax_id,
            'created_at' => $tenant->created_at?->toIso8601String(),
            'owners' => $tenant->users->map(fn (User $u) => ['name' => $u->name, 'email' => $u->email])->values(),
            'subscription' => $subscription === null ? null : [
                'status' => $subscription->status,
                'blocked' => $subscription->isBlocked(),
                'has_gateway_subscription' => $subscription->hasGatewaySubscription(),
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                'price_cents' => $subscription->priceCents(),
                'plans' => $subscription->plans->map(fn (Plan $plan) => PlanCatalogService::plan($plan))->values(),
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function planOptions(): array
    {
        return $this->plans->active()
            ->filter(fn (Plan $plan) => $plan->modules->count() === 1)
            ->map(fn (Plan $plan) => [...PlanCatalogService::plan($plan), 'is_public' => $plan->is_public])
            ->values()
            ->all();
    }
}
