<?php

namespace App\Platform\Contracts;

use App\Models\Tenant;
use App\Platform\Usage\UsageMeter;

/**
 * Cada módulo informa o próprio uso. Registre a implementação com a tag `usage-meters` no
 * service provider do módulo.
 */
interface UsageMeterContract
{
    /** Nulo quando o workspace não usa o módulo. */
    public function meter(Tenant $tenant): ?UsageMeter;
}
