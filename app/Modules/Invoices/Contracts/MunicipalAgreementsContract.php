<?php

namespace App\Modules\Invoices\Contracts;

use App\Modules\Invoices\Services\Adn\AgreementCheck;

/**
 * Convênios dos municípios com o Sistema Nacional NFS-e, lidos no ADN com o certificado da plataforma.
 */
interface MunicipalAgreementsContract
{
    /**
     * Consulta cada código numa mesma sessão mTLS e entrega o resultado ao callback, um por um.
     * Falha de um município vira AgreementCheck::unknown() e a varredura segue.
     *
     * @param  iterable<string>  $cityCodes
     * @param  callable(string, AgreementCheck): void  $onResult
     *
     * @throws \RuntimeException sem certificado da plataforma configurado.
     */
    public function check(string $environment, iterable $cityCodes, callable $onResult): void;
}
