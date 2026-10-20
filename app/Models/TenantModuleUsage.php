<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Contador de uso de um módulo por workspace e por dia (`period`). O mês é a soma dos dias.
 * Sem BelongsToTenant de propósito: quem conta (relay SMTP, API) passa o tenant explicitamente.
 */
class TenantModuleUsage extends Model
{
    protected $table = 'tenant_module_usage';

    protected $fillable = [
        'tenant_id',
        'module_id',
        'period',
        'count',
    ];

    protected function casts(): array
    {
        return [
            'period' => 'date',
            'count' => 'integer',
        ];
    }
}
