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
        $certs = [];

        if ($pfx === false || ! openssl_pkcs12_read($pfx, $certs, (string) $issuer->certificate_password)) {
            // O arquivo foi validado no envio; se não abre mais, a chave de criptografia do app mudou ou o dado corrompeu.
            throw ValidationException::withMessages([
                'issuer' => 'Não foi possível abrir o certificado do emitente. Envie o arquivo .pfx novamente.',
            ]);
        }

        return new IssuerCredentials(
            privateKeyPem: $certs['pkey'],
            certificatePem: $certs['cert'],
            chainPem: array_values($certs['extracerts'] ?? []),
        );
    }
}
