<?php

namespace App\Modules\Invoices\Services;

use App\Modules\Invoices\Contracts\IssuerRepositoryContract;
use App\Modules\Invoices\Models\Issuer;
use App\Modules\Signatures\Contracts\PlatformCertificateRepositoryContract;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Recifra o .pfx e a senha de todos os emitentes, e o da plataforma enviado pelo admin, com a
 * CERTIFICATES_ENCRYPTION_KEY atual. É o segundo passo da troca de chave: a antiga vai para
 * CERTIFICATES_ENCRYPTION_PREVIOUS_KEYS (para ainda ler), isto recifra tudo, e só então a antiga sai do .env.
 */
class CertificateKeyRotationService
{
    public function __construct(
        protected IssuerRepositoryContract $issuers,
        protected PlatformCertificateRepositoryContract $platformCertificates,
    ) {}

    /** @return array{reencrypted: int, failed: list<array{issuer_id: string, error: string}>} */
    public function reencryptAll(): array
    {
        $reencrypted = 0;
        $failed = [];

        foreach ($this->issuers->allWithCertificate() as $issuer) {
            try {
                $this->reencrypt($issuer);
                $reencrypted++;
            } catch (Throwable $e) {
                // Registro que nenhuma chave conhecida abre: segue com os outros e informa no fim.
                $failed[] = ['issuer_id' => $issuer->getKey(), 'error' => $e->getMessage()];
            }
        }

        // Os aposentados já não guardam .pfx: só o atual tem o que recifrar.
        $platform = $this->platformCertificates->current();
        if ($platform !== null) {
            try {
                $pfx = $platform->pfx;
                $password = $platform->password;
                DB::transaction(fn () => $platform->forceFill(['pfx' => $pfx, 'password' => $password])->save());
                $reencrypted++;
            } catch (Throwable $e) {
                $failed[] = ['issuer_id' => 'plataforma', 'error' => $e->getMessage()];
            }
        }

        return ['reencrypted' => $reencrypted, 'failed' => $failed];
    }

    protected function reencrypt(Issuer $issuer): void
    {
        // Ler pelo cast decifra com a chave atual ou uma anterior; gravar cifra sempre com a atual.
        $pfx = $issuer->certificate_pfx;
        $password = $issuer->certificate_password;

        DB::transaction(fn () => $issuer->forceFill([
            'certificate_pfx' => $pfx,
            'certificate_password' => $password,
        ])->save());
    }
}
