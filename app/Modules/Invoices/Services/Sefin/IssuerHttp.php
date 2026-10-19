<?php

namespace App\Modules\Invoices\Services\Sefin;

use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Services\Nfse\IssuerCredentials;
use App\Modules\Invoices\Services\Nfse\IssuerCredentialsLoader;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Chamada HTTP autenticada com o certificado do emitente (mTLS), usada pela Sefin Nacional e pelo ADN.
 */
class IssuerHttp
{
    protected const FILE_PREFIX = 'mdhb_nfse_';

    protected const RAM_DIRECTORY = '/dev/shm';

    /** Bem acima do timeout de qualquer sessão, para não apagar o arquivo de uma chamada em andamento. */
    protected const ORPHAN_AFTER_SECONDS = 3600;

    /** @var array<string, true> arquivos de credencial ainda no disco neste processo */
    protected static array $pending = [];

    protected static bool $shutdownRegistered = false;

    public function __construct(protected IssuerCredentialsLoader $credentials) {}

    /**
     * @param  string  $purpose  CertificateEvent::PURPOSE_SEFIN_AUTHENTICATION ou PURPOSE_ADN_AUTHENTICATION
     * @param  string  $operation  o que a chamada faz, para a trilha de auditoria (ex.: "emissão", "sincronização")
     * @param  callable(PendingRequest): Response  $send
     *
     * @throws ConnectionException
     */
    public function send(Issuer $issuer, string $purpose, string $operation, string $baseUrl, int $timeout, callable $send): Response
    {
        $credentials = $this->credentials->load($issuer, $purpose, ['operation' => $operation]);

        return $this->session($credentials, $baseUrl, $timeout, $send);
    }

    /**
     * Abre uma sessão mTLS com as credenciais dadas e entrega o cliente ao callback, que pode fazer
     * quantas chamadas quiser com o mesmo certificado.
     *
     * @template T
     *
     * @param  callable(PendingRequest): T  $run
     * @return T
     */
    public function session(#[\SensitiveParameter] IssuerCredentials $credentials, string $baseUrl, int $timeout, callable $run): mixed
    {
        // O Guzzle só aceita certificado de cliente por caminho de arquivo: CURLOPT_SSLKEY_BLOB fica fora da
        // lista dele porque o reaproveitamento de conexão é decidido pelo caminho, e com BLOB uma conexão
        // aberta com o certificado de um tenant poderia servir a outro. Então os PEM vão para arquivos em RAM.
        $directory = $this->secretsDirectory();
        $this->sweepOrphans($directory);

        // A cadeia vai junto do certificado para o servidor conseguir montar o caminho até a raiz ICP-Brasil.
        $certPath = $this->writeSecret($directory, $credentials->certificatePem.implode('', $credentials->chainPem));
        $keyPath = $this->writeSecret($directory, $credentials->privateKeyPem);

        try {
            return $run(Http::baseUrl(rtrim($baseUrl, '/'))
                ->acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->withOptions(['cert' => $certPath, 'ssl_key' => $keyPath]));
        } finally {
            self::forget($certPath);
            self::forget($keyPath);
        }
    }

    /**
     * /dev/shm é tmpfs: o arquivo vive em memória e some no reboot. Fora do Linux (dev no Mac) cai no tmp.
     */
    protected function secretsDirectory(): string
    {
        $base = is_dir(self::RAM_DIRECTORY) && is_writable(self::RAM_DIRECTORY) ? self::RAM_DIRECTORY : sys_get_temp_dir();
        $directory = $base.'/moduhub-mtls';

        if (! is_dir($directory) && ! @mkdir($directory, 0700) && ! is_dir($directory)) {
            throw new RuntimeException("Não foi possível criar o diretório das credenciais mTLS em {$base}.");
        }

        return $directory;
    }

    protected function writeSecret(string $directory, #[\SensitiveParameter] string $contents): string
    {
        // tempnam cria o arquivo já com 0600, antes de qualquer byte da chave ser escrito.
        $path = tempnam($directory, self::FILE_PREFIX);

        if ($path === false || file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Não foi possível preparar as credenciais mTLS do emitente.');
        }

        self::$pending[$path] = true;
        self::registerShutdownCleanup();

        return $path;
    }

    /**
     * Um worker morto por SIGKILL (timeout da fila, OOM killer) não roda nem o finally nem o shutdown.
     * O que ele deixou para trás é apagado na próxima sessão.
     */
    protected function sweepOrphans(string $directory): void
    {
        $threshold = time() - self::ORPHAN_AFTER_SECONDS;

        foreach (glob($directory.'/'.self::FILE_PREFIX.'*') ?: [] as $path) {
            if (@filemtime($path) < $threshold) {
                @unlink($path);
            }
        }
    }

    protected static function forget(string $path): void
    {
        @unlink($path);
        unset(self::$pending[$path]);
    }

    /** O finally não roda num erro fatal (memória esgotada, max_execution_time); o shutdown roda. */
    protected static function registerShutdownCleanup(): void
    {
        if (self::$shutdownRegistered) {
            return;
        }

        self::$shutdownRegistered = true;
        register_shutdown_function(static function (): void {
            foreach (array_keys(self::$pending) as $path) {
                self::forget($path);
            }
        });
    }
}
