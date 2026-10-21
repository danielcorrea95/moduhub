<?php

namespace App\Modules\Invoices\Services\Nfse;

use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Services\CertificateAuditService;
use App\Modules\Signatures\Services\PlatformCertificateService;
use Illuminate\Validation\ValidationException;

class IssuerCredentialsLoader
{
    public function __construct(
        protected CertificateAuditService $audit,
        protected PlatformCertificateService $platformCertificate,
    ) {}

    /**
     * Abre a chave privada do emitente. A finalidade é obrigatória e fica na trilha de auditoria:
     * não existe caminho que use o certificado do cliente sem deixar registro.
     *
     * @param  string  $purpose  uma das CertificateEvent::PURPOSE_*
     * @param  array<string, mixed>  $data  referência do que vai ser assinado ou consultado
     */
    public function load(Issuer $issuer, string $purpose, array $data = []): IssuerCredentials
    {
        if (! $issuer->hasCertificate() || $issuer->certificate_pfx === null) {
            throw ValidationException::withMessages([
                'issuer' => 'O emitente não tem certificado digital. Envie o e-CNPJ A1 na tela do emitente.',
            ]);
        }

        $pfx = base64_decode($issuer->certificate_pfx, true);
        $credentials = $pfx === false ? null : $this->fromPkcs12($pfx, (string) $issuer->certificate_password);

        if ($credentials === null) {
            // O arquivo foi validado no envio; se não abre mais, a CERTIFICATES_ENCRYPTION_KEY mudou ou o dado corrompeu.
            throw ValidationException::withMessages([
                'issuer' => 'Não foi possível abrir o certificado do emitente. Envie o arquivo .pfx novamente.',
            ]);
        }

        $this->audit->used($issuer, $purpose, $data);

        return $credentials;
    }

    /**
     * Certificado da própria plataforma (o mesmo do selo das assinaturas). Usado em consultas ao ADN
     * que não são de nenhum cliente, como a varredura dos convênios municipais.
     */
    public function platform(): ?IssuerCredentials
    {
        $pkcs12 = $this->platformCertificate->pkcs12();

        return $pkcs12 === null ? null : $this->fromPkcs12($pkcs12->binary, $pkcs12->password);
    }

    public function fromPkcs12(string $pfx, #[\SensitiveParameter] string $password): ?IssuerCredentials
    {
        $certs = [];
        if (! openssl_pkcs12_read($pfx, $certs, $password)) {
            return null;
        }

        return new IssuerCredentials(
            privateKeyPem: $certs['pkey'],
            certificatePem: $certs['cert'],
            chainPem: array_values($certs['extracerts'] ?? []),
        );
    }
}
