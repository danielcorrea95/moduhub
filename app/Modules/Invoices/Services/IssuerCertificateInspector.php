<?php

namespace App\Modules\Invoices\Services;

use App\Modules\Invoices\Models\Issuer;
use App\Modules\Signatures\Services\IcpCertificate;
use Illuminate\Validation\ValidationException;

/**
 * Regras do padrão nacional da NFS-e (Anexo I, Sefin/ADN) sobre o certificado do emitente:
 * precisa ser e-CNPJ da mesma empresa, de titular final, com uso de chave para assinatura
 * e não repúdio, e autenticação de cliente TLS para a conexão mTLS com a Sefin.
 */
class IssuerCertificateInspector
{
    public function assertUsableForNfse(IcpCertificate $certificate, Issuer $issuer): void
    {
        if ($certificate->cnpj === null) {
            throw ValidationException::withMessages([
                'certificate' => 'Este certificado não é um e-CNPJ: não encontramos o CNPJ do titular. Para emitir NFS-e, o certificado precisa ser da empresa emitente.',
            ]);
        }

        // A Sefin aceita certificado de qualquer estabelecimento da empresa, por isso a comparação é pela raiz.
        if (substr($certificate->cnpj, 0, 8) !== substr($issuer->cnpj, 0, 8)) {
            throw ValidationException::withMessages([
                'certificate' => sprintf(
                    'O certificado pertence ao CNPJ %s, mas o emitente é %s. Use um certificado da mesma empresa (matriz ou filial).',
                    $this->formatCnpj($certificate->cnpj),
                    $this->formatCnpj($issuer->cnpj),
                ),
            ]);
        }

        $extensions = $this->extensions($certificate);

        if (str_contains(strtoupper($extensions['basicConstraints'] ?? ''), 'CA:TRUE')) {
            throw ValidationException::withMessages([
                'certificate' => 'Este é um certificado de Autoridade Certificadora, não do titular. Envie o certificado da empresa.',
            ]);
        }

        // Quando a extensão existe ela precisa permitir o que a Sefin exige; certificados ICP-Brasil
        // reais sempre a trazem. Sem a extensão deixamos passar para não travar ambientes de teste.
        $keyUsage = $extensions['keyUsage'] ?? '';
        if ($keyUsage !== '' && (! str_contains($keyUsage, 'Digital Signature') || ! str_contains($keyUsage, 'Non Repudiation'))) {
            throw ValidationException::withMessages([
                'certificate' => 'O certificado não permite assinatura digital com não repúdio, exigidos pelo padrão nacional da NFS-e.',
            ]);
        }

        $extendedKeyUsage = $extensions['extendedKeyUsage'] ?? '';
        if ($extendedKeyUsage !== '' && ! str_contains($extendedKeyUsage, 'TLS Web Client Authentication')) {
            throw ValidationException::withMessages([
                'certificate' => 'O certificado não permite autenticação de cliente TLS, necessária para conectar à Sefin Nacional.',
            ]);
        }
    }

    public function fingerprint(IcpCertificate $certificate): string
    {
        return (string) openssl_x509_fingerprint($certificate->certPem, 'sha256');
    }

    /** @return array<string, string> */
    protected function extensions(IcpCertificate $certificate): array
    {
        $parsed = @openssl_x509_parse($certificate->certPem);

        return is_array($parsed) ? ($parsed['extensions'] ?? []) : [];
    }

    public function formatCnpj(string $cnpj): string
    {
        if (strlen($cnpj) !== 14) {
            return $cnpj;
        }

        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($cnpj, 0, 2),
            substr($cnpj, 2, 3),
            substr($cnpj, 5, 3),
            substr($cnpj, 8, 4),
            substr($cnpj, 12, 2),
        );
    }
}
