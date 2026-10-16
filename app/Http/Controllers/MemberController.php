<?php

namespace App\Http\Controllers;

use App\Http\Requests\Member\InviteMemberRequest;
use App\Http\Requests\Member\UpdateMemberRoleRequest;
use App\Models\TenantInvitation;
use App\Models\TenantUser;
use App\Models\User;
use App\Platform\Contracts\MemberRepositoryContract;
use App\Platform\Contracts\MemberServiceContract;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function __construct(
        protected MemberRepositoryContract $repo,
        protected MemberServiceContract $service,
    ) {}

    public function index(Request $request, CurrentTenant $current): Response
    {
        $tenant = $current->get();
        abort_if($tenant === null, 404);
        $this->authorize('view', $tenant);

        return Inertia::render('Workspaces/Members', [
            'members' => $this->repo->listMembers($tenant),
            'invitations' => $this->repo->pendingInvitations($tenant),
            'can' => [
                'manage' => $request->user()->can('manageMembers', $tenant),
            ],
            'roles' => [TenantUser::ROLE_ADMIN, TenantUser::ROLE_MEMBER],
        ]);
    }

    public function invite(InviteMemberRequest $request, CurrentTenant $current): RedirectResponse
    {
        $tenant = $current->get();
        abort_if($tenant === null, 404);

        $this->service->invite(
            $tenant,
            $request->user(),
            $request->string('email')->toString(),
            $request->string('role')->toString(),
        );

        $request->session()->flash('success', 'Convite criado. Copie o link para enviar.');

        return back();
    }

    public function revokeInvitation(Request $request, TenantInvitation $invitation, CurrentTenant $current): RedirectResponse
    {
        $tenant = $current->get();
        abort_if($tenant === null || $invitation->tenant_id !== $tenant->getKey(), 404);
        $this->authorize('manageMembers', $tenant);

        $this->service->revokeInvitation($invitation);
        $request->session()->flash('success', 'Convite revogado.');

        return back();
    }

    public function removeMember(Request $request, User $user, CurrentTenant $current): RedirectResponse
    {
        $tenant = $current->get();
        abort_if($tenant === null, 404);
        $this->authorize('manageMembers', $tenant);

        if ($user->getKey() === $request->user()->getKey()) {
            return back()->withErrors(['user' => 'Você não pode remover a si mesmo. Transfira o workspace primeiro.']);
        }

        $this->service->removeMember($tenant, $user);
        $request->session()->flash('success', 'Membro removido.');

        return back();
    }

    public function updateRole(UpdateMemberRoleRequest $request, User $user, CurrentTenant $current): RedirectResponse
    {
        $tenant = $current->get();
        abort_if($tenant === null, 404);

        $this->service->updateRole($tenant, $user, $request->string('role')->toString());
        $request->session()->flash('success', 'Role atualizado.');

        return back();
    }
}
