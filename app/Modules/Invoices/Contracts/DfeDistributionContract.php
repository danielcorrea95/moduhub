<?php

namespace App\Modules\Invoices\Contracts;

use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Services\Adn\DfeBatch;

/**
 * Distribuição de documentos do ADN para o contribuinte (GET /contribuintes/DFe/{NSU}), com o certificado
 * do emitente: as NFS-e em que ele aparece e todos os eventos delas, em ordem de NSU.
 */
interface DfeDistributionContract
{
    /** Lote de documentos com NSU maior que o informado. Nunca lança: falha vem em DfeBatch::$error. */
    public function fetch(Issuer $issuer, int $afterNsu): DfeBatch;
}
