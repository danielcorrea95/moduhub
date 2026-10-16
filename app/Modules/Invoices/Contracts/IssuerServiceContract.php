<?php

namespace App\Modules\Invoices\Contracts;

use App\Models\Tenant;
use App\Modules\Invoices\Models\Issuer;

interface IssuerServiceContract
{
    /** Cria ou atualiza o emitente do workspace com os dados cadastrais e o enquadramento tributário. */
    public function configure(Tenant $tenant, array $attrs): Issuer;

    /**
     * Valida e guarda o certificado A1 (.pfx) do emitente.
     *
     * @throws \Illuminate\Validation\ValidationException quando a senha está errada, o certificado
     *         não serve para NFS-e ou pertence a outra empresa.
     */
    public function attachCertificate(Tenant $tenant, string $pkcs12Binary, string $password): Issuer;

    public function removeCertificate(Tenant $tenant): Issuer;
}
