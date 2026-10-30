<?php

namespace App\Platform\Contracts;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TenantRepositoryContract
{
    public function workspacesForUser(User $user): Collection;

    public function activeModuleKeys(Tenant $tenant): array;

    /**
     * Todos os workspaces, para o admin da plataforma: com os donos e as assinaturas (com planos).
     * A busca olha nome, slug, CNPJ e e-mail do dono.
     *
     * @return LengthAwarePaginator<int, Tenant>
     */
    public function paginateForAdmin(?string $search, int $perPage = 30): LengthAwarePaginator;

    /** O workspace com os donos e as assinaturas, como na listagem do admin. */
    public function loadForAdmin(Tenant $tenant): Tenant;
}
