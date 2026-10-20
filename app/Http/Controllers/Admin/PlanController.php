<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePlanRequest;
use App\Models\Module;
use App\Models\Plan;
use App\Platform\Contracts\PlanRepositoryContract;
use App\Platform\Services\PlanAdminService;
use App\Platform\Services\PlanCatalogService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function __construct(
        protected PlanRepositoryContract $plans,
        protected PlanAdminService $service,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Plans/Index', [
            'plans' => $this->plans->allForAdmin()->map(fn (Plan $plan) => [
                ...PlanCatalogService::plan($plan),
                'is_active' => $plan->is_active,
                'is_public' => $plan->is_public,
                'sort_order' => $plan->sort_order,
                'combo' => $plan->modules->count() > 1,
                'subscribers' => $plan->subscription_items_count,
            ])->values(),
            'products' => self::products(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Plans/Form', ['plan' => null, 'products' => self::products()]);
    }

    public function store(SavePlanRequest $request): RedirectResponse
    {
        $plan = $this->service->create($request->validated());

        return redirect()->route('admin.plans.index')->with('success', "Plano {$plan->name} criado.");
    }

    public function edit(Plan $plan): Response
    {
        $plan->load('modules')->loadCount('subscriptionItems');

        return Inertia::render('Admin/Plans/Form', [
            'plan' => [
                ...PlanCatalogService::plan($plan),
                'is_active' => $plan->is_active,
                'is_public' => $plan->is_public,
                'sort_order' => $plan->sort_order,
                'subscribers' => $plan->subscription_items_count,
            ],
            'products' => self::products(),
        ]);
    }

    public function update(SavePlanRequest $request, Plan $plan): RedirectResponse
    {
        $this->service->update($plan, $request->validated());

        return redirect()->route('admin.plans.index')->with('success', "Plano {$plan->name} salvo.");
    }

    /** @return list<array{key: string, name: string}> */
    private static function products(): array
    {
        return [
            ['key' => Module::SIGNATURES, 'name' => 'Assinatura de documentos'],
            ['key' => Module::INVOICES, 'name' => 'Notas fiscais (NFS-e)'],
            ['key' => Module::MAILING, 'name' => 'Disparo de e-mails'],
        ];
    }
}
