<?php

namespace App\Modules\Invoices\Contracts;

use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Services\Adn\ServiceCodeCheck;
use Carbon\CarbonInterface;

/**
 * Parâmetros que cada município cadastra no Sistema Nacional NFS-e, consultados no ADN com o
 * certificado do emitente. Nunca lança exceção: falta de resposta vira ServiceCodeCheck::unknown().
 */
interface MunicipalParametersContract
{
    public function serviceRate(Issuer $issuer, string $cityCode, string $nationalCode, ?string $municipalCode, CarbonInterface $competence): ServiceCodeCheck;
}
