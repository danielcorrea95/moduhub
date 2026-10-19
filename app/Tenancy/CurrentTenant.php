<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Closure;
use LogicException;

class CurrentTenant
{
    protected ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?string
    {
        return $this->tenant?->getKey();
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }

    /**
     * Executa o callback dentro do workspace dado e devolve o contexto anterior no fim, mesmo com erro.
     * Para jobs, comandos e rotas públicas que descobrem o tenant pelo registro ou pelo token, e para
     * serviços que recebem o tenant como argumento.
     *
     * Trocar para outro workspace com um já em vigor é recusado: um request do tenant A agindo
     * sobre o tenant B é bug, não algo a acomodar.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;

        if ($previous !== null && $previous->getKey() !== $tenant->getKey()) {
            throw new LogicException("Tentativa de agir no tenant {$tenant->getKey()} dentro do contexto do tenant {$previous->getKey()}.");
        }

        $this->tenant = $tenant;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }
}
