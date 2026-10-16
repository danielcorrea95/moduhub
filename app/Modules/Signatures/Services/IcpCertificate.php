<?php

namespace App\Modules\Signatures\Services;

use Carbon\CarbonImmutable;

final readonly class IcpCertificate
{
    public function __construct(
        public string $certPem,
        public string $privateKeyPem,
        public string $subjectCn,
        public ?string $cpf,
        public ?string $cnpj,
        public string $issuerCn,
        public string $serial,
        public CarbonImmutable $validFrom,
        public CarbonImmutable $validTo,
    ) {}

    public function kind(): string
    {
        return $this->cnpj !== null ? 'pj' : 'pf';
    }
}
