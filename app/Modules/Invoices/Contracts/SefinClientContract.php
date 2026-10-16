<?php

namespace App\Modules\Invoices\Contracts;

use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Services\Sefin\SefinResponse;
use App\Modules\Invoices\Services\Sefin\SefinUnavailableException;

/**
 * API da Sefin Nacional. Toda chamada autentica com o certificado do emitente (mTLS).
 *
 * Rejeição de negócio volta como SefinResponse com `successful = false`; falta de resposta
 * (rede, timeout, 5xx) lança SefinUnavailableException, porque aí não se sabe se a nota saiu.
 */
interface SefinClientContract
{
    /** POST /nfse: envia a DPS assinada e recebe a NFS-e gerada ou a rejeição. */
    public function emit(Issuer $issuer, string $signedDpsXml): SefinResponse;

    /**
     * GET /dps/{id}: chave de acesso da NFS-e gerada a partir da DPS, ou null se não houver.
     *
     * @throws SefinUnavailableException
     */
    public function findAccessKeyByDps(Issuer $issuer, string $dpsId): ?string;

    /** GET /nfse/{chave}: XML da NFS-e. */
    public function fetch(Issuer $issuer, string $accessKey): SefinResponse;

    /** POST /nfse/{chave}/eventos: registra um evento assinado (cancelamento). */
    public function registerEvent(Issuer $issuer, string $accessKey, string $signedEventXml): SefinResponse;
}
