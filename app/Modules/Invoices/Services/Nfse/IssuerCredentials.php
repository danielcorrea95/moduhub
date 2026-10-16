<?php

namespace App\Modules\Invoices\Services\Nfse;

/**
 * Chave privada e certificado do emitente em PEM, extraídos do .pfx só pelo tempo de uma operação.
 */
final readonly class IssuerCredentials
{
    public function __construct(
        public string $privateKeyPem,
        public string $certificatePem,
        /** @var list<string> certificados intermediários da cadeia, em PEM */
        public array $chainPem = [],
    ) {}

    /** Certificado em base64 (DER), como vai no X509Certificate da assinatura. */
    public function certificateBase64(): string
    {
        return preg_replace('/-----[A-Z ]+-----|\s+/', '', $this->certificatePem) ?? '';
    }
}
