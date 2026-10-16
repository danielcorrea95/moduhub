<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Platform\Contracts\InvitationRepositoryContract;
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

        return Inertia::render('Auth/Register', [
            'invitation' => $invitation,
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
