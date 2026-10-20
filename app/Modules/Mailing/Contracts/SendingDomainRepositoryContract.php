<?php

namespace App\Modules\Mailing\Contracts;

use App\Models\Tenant;
use App\Modules\Mailing\Models\SendingDomain;
use Illuminate\Support\LazyCollection;

interface SendingDomainRepositoryContract
{
    /** Busca pelo tenant do argumento, com ou sem tenant no contexto (worker de fila monta e-mail). */
    public function forTenant(Tenant $tenant): ?SendingDomain;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): SendingDomain;

    public function save(SendingDomain $domain): SendingDomain;

    public function delete(SendingDomain $domain): void;

    /** @return LazyCollection<int, SendingDomain> de todos os workspaces, para a conferência agendada */
    public function allForMonitoring(): LazyCollection;
}
