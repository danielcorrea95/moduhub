<?php

namespace App\Modules\Invoices\Console;

use App\Modules\Invoices\Contracts\IssuerRepositoryContract;
use App\Modules\Invoices\Services\CertificateExpiryService;
use App\Tenancy\CurrentTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckCertificateExpiry extends Command
{
    protected $signature = 'invoices:check-certificates';

    protected $description = 'Avisa os workspaces cujo certificado do emitente vence em 30, 15, 7 ou 1 dia, ou já venceu';

    public function handle(IssuerRepositoryContract $issuers, CertificateExpiryService $expiry, CurrentTenant $tenants): int
    {
        foreach ($issuers->allWithCertificate() as $issuer) {
            try {
                $daysLeft = $tenants->run($issuer->tenant, fn () => $expiry->notifyIfDue($issuer));
            } catch (Throwable $e) {
                // Um e-mail que falha não pode impedir o aviso dos outros workspaces; amanhã tenta de novo.
                Log::error('Falha ao avisar vencimento do certificado do emitente', ['issuer_id' => $issuer->getKey(), 'error' => $e->getMessage()]);
                $this->warn("{$issuer->legal_name}: falhou ({$e->getMessage()})");

                continue;
            }

            if ($daysLeft !== null) {
                $this->info($daysLeft === 0 ? "{$issuer->legal_name}: avisado que o certificado venceu" : "{$issuer->legal_name}: avisado, vence em {$daysLeft} dia(s)");
            }
        }

        return self::SUCCESS;
    }
}
