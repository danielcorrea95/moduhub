<?php

namespace App\Modules\Signatures\Services;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class IcpCertificateService
{
    /**
     * Parses a PKCS#12 (.pfx) binary with its password and returns an
     * IcpCertificate value object containing the PEM-encoded cert, private
     * key and the extracted identity fields. Throws ValidationException with
     * a user-facing message on any failure (bad password, expired cert, etc).
     */
    public function parse(string $pkcs12Binary, string $password): IcpCertificate
    {
        $certs = [];
        if (! @openssl_pkcs12_read($pkcs12Binary, $certs, $password)) {
            throw ValidationException::withMessages([
                'password' => 'Não foi possível ler o certificado. Verifique a senha.',
            ]);
        }

        if (empty($certs['cert']) || empty($certs['pkey'])) {
            throw ValidationException::withMessages([
                'certificate' => 'Certificado incompleto (sem par chave/certificado).',
            ]);
        }

        $parsed = @openssl_x509_parse($certs['cert']);
        if (! is_array($parsed)) {
            throw ValidationException::withMessages([
                'certificate' => 'Formato de certificado inválido.',
            ]);
        }

        $validFrom = CarbonImmutable::createFromTimestamp($parsed['validFrom_time_t'] ?? 0);
        $validTo = CarbonImmutable::createFromTimestamp($parsed['validTo_time_t'] ?? 0);

        if ($validTo->isPast()) {
            throw ValidationException::withMessages([
                'certificate' => 'Certificado expirado em ' . $validTo->format('d/m/Y') . '.',
            ]);
        }
        if ($validFrom->isFuture()) {
            throw ValidationException::withMessages([
                'certificate' => 'Certificado ainda não é válido (início em ' . $validFrom->format('d/m/Y') . ').',
            ]);
        }

        $subjectCn = (string) ($parsed['subject']['CN'] ?? '');
        $issuerCn = (string) ($parsed['issuer']['CN'] ?? '');
        $serial = isset($parsed['serialNumberHex'])
            ? (string) $parsed['serialNumberHex']
            : (string) ($parsed['serialNumber'] ?? '');

        return new IcpCertificate(
            certPem: $certs['cert'],
            privateKeyPem: $certs['pkey'],
            subjectCn: $subjectCn,
            cpf: $this->extractCpfFromCn($subjectCn),
            cnpj: $this->extractCnpjFromCn($subjectCn),
            issuerCn: $issuerCn,
            serial: $serial,
            validFrom: $validFrom,
            validTo: $validTo,
        );
    }

    /**
     * e-CPF A1 certificates encode the CPF inside the CN as "NAME:CPF"
     * (e.g. "JOAO DA SILVA:12345678901"). The pattern is exactly 11 digits.
     * Rejects strings with 14 contiguous digits so we don't collide with CNPJ.
     */
    protected function extractCpfFromCn(string $cn): ?string
    {
        if ($cn === '') {
            return null;
        }

        if (preg_match('/:(\d{11})(?!\d)/', $cn, $match)) {
            return $match[1];
        }
        if (preg_match('/(?<!\d)(\d{11})(?!\d)/', $cn, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * e-CNPJ A1 certificates encode the CNPJ inside the CN as
     * "RAZAO SOCIAL:CNPJ" with 14 digits.
     */
    protected function extractCnpjFromCn(string $cn): ?string
    {
        if ($cn === '') {
            return null;
        }

        if (preg_match('/:(\d{14})(?!\d)/', $cn, $match)) {
            return $match[1];
        }
        if (preg_match('/(?<!\d)(\d{14})(?!\d)/', $cn, $match)) {
            return $match[1];
        }

        return null;
    }

    public function normalizeDigits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    public function normalizeCpf(string $cpf): string
    {
        return $this->normalizeDigits($cpf);
    }
}
