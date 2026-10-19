<?php

namespace App\Modules\Invoices\Services\Sefin;

use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Services\Nfse\IssuerCredentials;
use App\Modules\Invoices\Services\Nfse\IssuerCredentialsLoader;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Chamada HTTP autenticada com o certificado do emitente (mTLS), usada pela Sefin Nacional e pelo ADN.
 */
class IssuerHttp
{
    public function __construct(protected IssuerCredentialsLoader $credentials) {}

    /**
     * @param  callable(PendingRequest): Response  $send
     *
     * @throws ConnectionException
     */
    public function send(Issuer $issuer, string $baseUrl, int $timeout, callable $send): Response
    {
        return $this->session($this->credentials->load($issuer), $baseUrl, $timeout, $send);
    }

    /**
     * Abre uma sessão mTLS com as credenciais dadas e entrega o cliente ao callback, que pode fazer
     * quantas chamadas quiser: os PEM temporários são escritos uma vez só, e apagados no fim.
     *
     * @template T
     *
     * @param  callable(PendingRequest): T  $run
     * @return T
     */
    public function session(IssuerCredentials $credentials, string $baseUrl, int $timeout, callable $run): mixed
    {
        // O Guzzle só aceita certificado de cliente por caminho de arquivo. Os PEM vivem o tempo da chamada.
        $certPath = $this->tempFile($credentials->certificatePem.implode('', $credentials->chainPem));
        $keyPath = $this->tempFile($credentials->privateKeyPem);

        try {
            return $run(Http::baseUrl(rtrim($baseUrl, '/'))
                ->acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->withOptions(['cert' => $certPath, 'ssl_key' => $keyPath]));
        } finally {
            @unlink($certPath);
            @unlink($keyPath);
        }
    }

    protected function tempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mdhb_nfse_');
        chmod($path, 0600);
        file_put_contents($path, $contents);

        return $path;
    }
}
