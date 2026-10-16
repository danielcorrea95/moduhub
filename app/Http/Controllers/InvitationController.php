<?php

namespace App\Http\Controllers;

use App\Models\TenantInvitation;
use App\Platform\Contracts\InvitationRepositoryContract;
use App\Platform\Contracts\InvitationServiceContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function __construct(
        protected InvitationRepositoryContract $repo,
        protected InvitationServiceContract $service,
    ) {}

    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $invitation = $this->repo->findActiveByToken($token);

        if ($invitation === null) {
            return Inertia::render('Invitations/Invalid');
        }

        $user = $request->user();

        if ($user === null) {
            return Inertia::render('Invitations/Show', [
                'invitation' => $this->payload($invitation),
                'needs_account' => true,
                'register_url' => route('register', ['invitation' => $token]),
                'login_url' => route('login', ['invitation' => $token]),
            ]);
        }

        if (strcasecmp($invitation->email, $user->email) !== 0) {
            return Inertia::render('Invitations/Mismatch', [
                'invitation' => $this->payload($invitation),
                'current_email' => $user->email,
            ]);
        }

        return Inertia::render('Invitations/Show', [
            'invitation' => $this->payload($invitation),
            'needs_account' => false,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->repo->findByToken($token);
        abort_if($invitation === null, 404);
        abort_if($request->user() === null, 401);

        $this->service->accept($invitation, $request->user());

        $request->session()->put('current_tenant_id', $invitation->tenant_id);
        $request->session()->flash('success', 'Você entrou no workspace ' . $invitation->tenant->name . '.');

        return redirect()->route('dashboard');
    }

    protected function payload(TenantInvitation $invitation): array
    {
        return [
            'token' => $invitation->token,
            'email' => $invitation->email,
            'role' => $invitation->role,
            'tenant_name' => $invitation->tenant->name,
            'expires_at' => $invitation->expires_at,
        ];
    }
}
