<?php

namespace App\Modules\Invoices\Services\Nfse;

use App\Modules\Invoices\Models\Issuer;
use Illuminate\Validation\ValidationException;

class IssuerCredentialsLoader
{
    public function load(Issuer $issuer): IssuerCredentials
    {
        if (! $issuer->hasCertificate() || $issuer->certificate_pfx === null) {
            throw ValidationException::withMessages([
                'issuer' => 'O emitente não tem certificado digital. Envie o e-CNPJ A1 na tela do emitente.',
            ]);
        }

        $pfx = base64_decode($issuer->certificate_pfx, true);
        $credentials = $pfx === false ? null : $this->fromPkcs12($pfx, (string) $issuer->certificate_password);

        if ($credentials === null) {
            // O arquivo foi validado no envio; se não abre mais, a chave de criptografia do app mudou ou o dado corrompeu.
            throw ValidationException::withMessages([
                'issuer' => 'Não foi possível abrir o certificado do emitente. Envie o arquivo .pfx novamente.',
            ]);
        }

        return $credentials;
    }

    /**
     * Certificado da própria plataforma (o mesmo do selo das assinaturas). Usado em consultas ao ADN
     * que não são de nenhum cliente, como a varredura dos convênios municipais.
     */
    public function platform(): ?IssuerCredentials
    {
        $path = (string) config('signatures.seal.certificate_path');
        $password = config('signatures.seal.certificate_password');

        if ($password === null || ! is_readable($path)) {
            return null;
        }

        return $this->fromPkcs12((string) file_get_contents($path), (string) $password);
    }

    public function fromPkcs12(string $pfx, string $password): ?IssuerCredentials
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
