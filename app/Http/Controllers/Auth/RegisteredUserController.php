<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Platform\Contracts\InvitationRepositoryContract;
use App\Platform\Contracts\PlanRepositoryContract;
use App\Platform\Contracts\RegistrationServiceContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function __construct(
        protected RegistrationServiceContract $registration,
        protected InvitationRepositoryContract $invitations,
        protected PlanRepositoryContract $plans,
    ) {}

    public function create(Request $request): Response
    {
        $invitation = null;
        if ($token = $request->query('invitation')) {
            $found = $this->invitations->findActiveByToken($token);
            if ($found !== null) {
                $invitation = [
                    'token' => $found->token,
                    'email' => $found->email,
                    'tenant_name' => $found->tenant->name,
                ];
            }
        }

        // ?plans=assinaturas-starter,nfse-100 vem do carrinho da página de vendas.
        $keys = array_filter(explode(',', (string) $request->query('plans', '')));
        $plans = $invitation === null && $keys !== [] ? $this->plans->findManyForSaleByKeys($keys) : collect();

        return Inertia::render('Auth/Register', [
            'invitation' => $invitation,
            'plans' => $plans->map(fn ($plan) => [
                'key' => $plan->key,
                'name' => $plan->name,
                'price_cents' => (int) $plan->price_cents,
                'trial_days' => (int) $plan->trial_days,
            ])->values(),
        ]);
    }

    public function store(RegisterUserRequest $request): RedirectResponse
    {
        $result = $this->registration->register($request->validated());

        Auth::login($result['user']);
        $request->session()->put('current_tenant_id', $result['tenant_id']);

        return redirect(route('dashboard', absolute: false));
    }
}
