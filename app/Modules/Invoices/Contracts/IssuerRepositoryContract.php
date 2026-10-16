<?php

namespace App\Modules\Invoices\Contracts;

use App\Modules\Invoices\Models\Issuer;

interface IssuerRepositoryContract
{
    /** O emitente do workspace atual, resolvido pelo TenantScope. */
    public function forCurrentTenant(): ?Issuer;

    /**
     * Relê o emitente com lock de linha, para reservar o próximo número de DPS sem colisão
     * entre emissões simultâneas. Só tem efeito dentro de uma transação.
     */
    public function lockForNumbering(Issuer $issuer): Issuer;
}
