<?php

namespace App\Modules\Signatures\Console;

use App\Modules\Signatures\Services\PlatformCertificateMonitor;
use Illuminate\Console\Command;

class CheckPlatformCertificate extends Command
{
    protected $signature = 'signatures:check-platform-certificate';

    protected $description = 'Alerta a operação se o certificado da plataforma (selo ICP-Brasil) vence em 30, 15, 7 ou 1 dia, ou não abre';

    public function handle(PlatformCertificateMonitor $monitor): int
    {
        $message = $monitor->check();

        $message === null ? $this->info('Certificado da plataforma sem pendências.') : $this->warn($message);

        return self::SUCCESS;
    }
}
