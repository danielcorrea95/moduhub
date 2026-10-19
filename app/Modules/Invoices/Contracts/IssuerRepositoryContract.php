<?php

namespace App\Modules\Invoices\Contracts;

use App\Modules\Invoices\Models\Issuer;

interface IssuerRepositoryContract
{
    /** O emitente do workspace atual, resolvido pelo TenantScope. */
    public function forCurrentTenant(): ?Issuer;

    /**
     * O emitente de um workspace dado. Quem vai usar o certificado deve vir por aqui: sem tenant no
     * contexto (job, comando) o TenantScope não filtra, e forCurrentTenant() traria o emitente de outro cliente.
     */
    public function forTenant(string $tenantId): ?Issuer;

    /**
     * Relê o emitente com lock de linha, para reservar o próximo número de DPS sem colisão
     * entre emissões simultâneas. Só tem efeito dentro de uma transação.
     */
    public function lockForNumbering(Issuer $issuer): Issuer;
}
