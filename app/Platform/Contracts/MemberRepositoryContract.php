<?php

namespace App\Platform\Contracts;

use App\Models\Tenant;
use Illuminate\Support\Collection;

interface MemberRepositoryContract
{
    public function listMembers(Tenant $tenant): Collection;

    public function pendingInvitations(Tenant $tenant): Collection;

    public function hasMemberWithEmail(Tenant $tenant, string $email): bool;

    /** Donos e administradores do workspace: quem recebe avisos que pedem ação (certificado vencendo etc.). */
    public function managers(Tenant $tenant): Collection;
}
