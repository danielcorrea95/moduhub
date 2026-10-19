<?php

namespace App\Modules\Invoices\Console;

use App\Modules\Invoices\Services\CertificateKeyRotationService;
use Illuminate\Console\Command;

class ReencryptCertificates extends Command
{
    protected $signature = 'invoices:reencrypt-certificates';

    protected $description = 'Recifra os certificados dos emitentes com a CERTIFICATES_ENCRYPTION_KEY atual (troca de chave)';

    public function handle(CertificateKeyRotationService $rotation): int
    {
        $result = $rotation->reencryptAll();

        $this->info("{$result['reencrypted']} certificado(s) recifrado(s) com a chave atual.");

        if ($result['failed'] !== []) {
            $this->error(count($result['failed']).' não abriram com nenhuma chave conhecida. Não remova as chaves antigas ainda.');
            $this->table(['Emitente', 'Erro'], array_map(fn ($f) => [$f['issuer_id'], $f['error']], $result['failed']));

            return self::FAILURE;
        }

        $this->line('Agora já é seguro tirar a chave antiga de CERTIFICATES_ENCRYPTION_PREVIOUS_KEYS.');

        return self::SUCCESS;
    }
}
