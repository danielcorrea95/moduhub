<?php

namespace App\Platform\Contracts;

use App\Models\Tenant;
use App\Models\User;
use App\Platform\Dashboard\DashboardSection;

/**
 * Pedaço da tela inicial que um módulo entrega. Devolve null quando o módulo não está contratado
 * ou o usuário não pode ver aqueles dados.
 */
interface DashboardSectionContract
{
    public function section(Tenant $tenant, User $user): ?DashboardSection;
}
