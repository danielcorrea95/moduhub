<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class MissingTenantContext extends LogicException
{
    public static function forModel(Model $model): self
    {
        return new self(sprintf(
            'Consulta em %s sem tenant no contexto. Use CurrentTenant::run() para trabalhar dentro de um workspace, '
            .'ou withoutTenantScope() quando atravessar workspaces for intencional.',
            $model::class,
        ));
    }
}
