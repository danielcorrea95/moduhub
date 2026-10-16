<?php

namespace App\Modules\Invoices\Policies;

use App\Models\TenantUser;
use App\Models\User;
use App\Tenancy\CurrentTenant;

/**
 * O emitente é configuração do workspace, não um registro que o usuário cria: as regras olham
 * para o papel da pessoa no workspace atual, por isso os métodos recebem só o usuário.
 */
class IssuerPolicy
{
    public function __construct(protected CurrentTenant $currentTenant) {}

    public function view(User $user): bool
    {
        $tenant = $this->currentTenant->get();

        return $tenant !== null && $user->belongsToTenant($tenant);
    }

    /** Dados fiscais e certificado digital só por quem administra o workspace. */
    public function manage(User $user): bool
    {
        $tenant = $this->currentTenant->get();
        if ($tenant === null) {
            return false;
        }

        return in_array($user->tenantRole($tenant), [TenantUser::ROLE_OWNER, TenantUser::ROLE_ADMIN], true);
    }
}
