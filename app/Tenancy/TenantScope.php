<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Fechado por padrão: sem tenant no contexto a consulta não roda. Antes ela rodava sem filtro, e um job
 * ou comando via os registros de todos os workspaces. Quem precisa atravessar tenants diz isso com
 * withoutTenantScope(); quem trabalha para um tenant entra no contexto dele com CurrentTenant::run().
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(CurrentTenant::class)->id();

        if ($tenantId === null) {
            throw MissingTenantContext::forModel($model);
        }

        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
    }
}
