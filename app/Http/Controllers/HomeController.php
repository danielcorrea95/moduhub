<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\Plan;
use App\Platform\Contracts\PlanRepositoryContract;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(protected PlanRepositoryContract $plans) {}

    public function __invoke(): Response
    {
        return Inertia::render('Welcome', [
            'canRegister' => Route::has('register'),
            'plans' => $this->plans->publicCatalog()->map(fn (Plan $plan) => [
                'key' => $plan->key,
                'name' => $plan->name,
                'description' => $plan->description,
                'price_cents' => $plan->price_cents,
                'currency' => $plan->currency,
                'billing_period' => $plan->billing_period,
                'trial_days' => $plan->trial_days,
                'modules' => $plan->modules->map(fn (Module $m) => [
                    'key' => $m->key,
                    'name' => $m->name,
                    'released' => $m->isReleased(),
                    'included_quota' => $m->pivot->included_quota,
                    'overage_cents' => $m->pivot->overage_cents,
                ])->values(),
            ])->values(),
        ]);
    }
}
