<?php

namespace App\Modules\Signatures\Services;

use Illuminate\Validation\ValidationException;

/**
 * Confere se o certificado foi emitido dentro da ICP-Brasil: a cadeia precisa chegar a uma das
 * raízes do ITI em resources/certificates/icp-brasil/roots.pem. Sem isso, qualquer um gera um
 * certificado autoassinado com o CNPJ que quiser no nome.
 *
 * As ACs intermediárias vêm do próprio .pfx e, para quem exportou sem a cadeia, do pacote do ITI.
 * Elas só ajudam a montar o caminho: confiança, só nas raízes. Revogação (LCR) não é consultada.
 */
class IcpChainValidator
{
    public function assertTrusted(IcpCertificate $certificate): void
    {
        $roots = (string) config('signatures.icp.roots_path');

        if (! is_readable($roots)) {
            throw ValidationException::withMessages([
                'certificate' => 'As raízes da ICP-Brasil não estão instaladas no servidor. Não é possível conferir o certificado.',
            ]);
        }

        $scratch = new LocalScratch();

        try {
            $untrusted = $this->intermediates($certificate);
            $path = null;

            if ($untrusted !== '') {
                $path = $scratch->create();
                file_put_contents($path, $untrusted);
            }

            $trusted = $path === null
                ? openssl_x509_checkpurpose($certificate->certPem, X509_PURPOSE_ANY, [$roots])
                : openssl_x509_checkpurpose($certificate->certPem, X509_PURPOSE_ANY, [$roots], $path);
        } finally {
            $scratch->cleanup();
        }

        if ($trusted !== true) {
            throw ValidationException::withMessages([
                'certificate' => 'O certificado não foi emitido por uma autoridade da ICP-Brasil (ou a cadeia dele não fecha até a raiz do ITI).',
            ]);
        }
    }

    protected function intermediates(IcpCertificate $certificate): string
    {
        $bundle = (string) config('signatures.icp.intermediates_path');
        $pems = $certificate->chainPem;

        if ($bundle !== '' && is_readable($bundle)) {
            $pems[] = (string) file_get_contents($bundle);
        }

        return trim(implode("\n", array_map('trim', $pems)));
    }
}
