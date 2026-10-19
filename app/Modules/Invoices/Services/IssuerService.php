<?php

namespace App\Modules\Invoices\Services;

use App\Models\Tenant;
use App\Modules\Invoices\Contracts\IssuerServiceContract;
use App\Modules\Invoices\Models\Issuer;
use App\Modules\Signatures\Services\IcpCertificate;
use App\Modules\Signatures\Services\IcpCertificateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IssuerService implements IssuerServiceContract
{
    public function __construct(
        protected IcpCertificateService $certificates,
        protected IssuerCertificateInspector $inspector,
        protected CertificateAuditService $audit,
    ) {}

    public function configure(Tenant $tenant, array $attrs): Issuer
    {
        $attrs['cnpj'] = $this->normalizeCnpj((string) $attrs['cnpj']);
        $attrs['municipal_registration'] = $this->normalizeAlphanumeric($attrs['municipal_registration'] ?? null);
        $attrs['phone'] = isset($attrs['phone']) && $attrs['phone'] !== '' ? $this->certificates->normalizeDigits((string) $attrs['phone']) : null;
        $attrs['state'] = isset($attrs['state']) && $attrs['state'] !== '' ? strtoupper((string) $attrs['state']) : null;
        $attrs['dps_series'] = ltrim((string) ($attrs['dps_series'] ?? '1'), '0') ?: '1';

        // O regime de apuração do Simples só existe para ME/EPP optante; nos demais casos não vai para a DPS.
        if ((int) ($attrs['simples_nacional'] ?? 0) !== Issuer::SIMPLES_ME_EPP) {
            $attrs['simples_regime'] = null;
        }

        return DB::transaction(function () use ($tenant, $attrs) {
            // Busca explícita pelo tenant recebido, e não pelo scope global: o chamador diz de quem é o
            // emitente, e assim o serviço funciona igual no painel, na API e em jobs sem sessão.
            $issuer = Issuer::query()
                ->withoutTenantScope()
                ->firstOrNew(['tenant_id' => $tenant->getKey()]);

            $issuer->fill($attrs);
            $issuer->tenant_id = $tenant->getKey();
            $issuer->save();

            return $issuer;
        });
    }

    public function attachCertificate(Tenant $tenant, string $pkcs12Binary, #[\SensitiveParameter] string $password): Issuer
    {
        $issuer = $this->requireIssuer($tenant);

        $certificate = $this->certificates->parse($pkcs12Binary, $password);
        $this->inspector->assertUsableForNfse($certificate, $issuer);
        $previousFingerprint = $issuer->certificate_fingerprint;

        return DB::transaction(function () use ($issuer, $certificate, $pkcs12Binary, $password, $previousFingerprint) {
            $this->storeCertificate($issuer, $certificate, $pkcs12Binary, $password);
            $this->audit->uploaded($issuer, $previousFingerprint);

            return $issuer;
        });
    }

    protected function storeCertificate(Issuer $issuer, IcpCertificate $certificate, string $pkcs12Binary, #[\SensitiveParameter] string $password): void
    {
        $issuer->forceFill([
            'certificate_pfx' => base64_encode($pkcs12Binary),
            'certificate_password' => $password,
            'certificate_subject' => $certificate->subjectCn,
            'certificate_cnpj' => $certificate->cnpj,
            'certificate_issuer' => $certificate->issuerCn,
            'certificate_serial' => $certificate->serial,
            'certificate_fingerprint' => $this->inspector->fingerprint($certificate),
            'certificate_valid_from' => $certificate->validFrom,
            'certificate_valid_until' => $certificate->validTo,
            'certificate_uploaded_at' => now(),
            // Certificado novo, contagem de avisos de vencimento do zero.
            'certificate_expiry_notice_days' => null,
        ])->save();
    }

    public function removeCertificate(Tenant $tenant): Issuer
    {
        $issuer = $this->requireIssuer($tenant);

        if (! $issuer->hasCertificate()) {
            return $issuer;
        }

        return DB::transaction(function () use ($issuer) {
            $this->audit->removed($issuer);
            $this->clearCertificate($issuer);

            return $issuer;
        });
    }

    protected function clearCertificate(Issuer $issuer): void
    {
        $issuer->forceFill([
            'certificate_pfx' => null,
            'certificate_password' => null,
            'certificate_subject' => null,
            'certificate_cnpj' => null,
            'certificate_issuer' => null,
            'certificate_serial' => null,
            'certificate_fingerprint' => null,
            'certificate_valid_from' => null,
            'certificate_valid_until' => null,
            'certificate_uploaded_at' => null,
            'certificate_expiry_notice_days' => null,
        ])->save();
    }

    protected function requireIssuer(Tenant $tenant): Issuer
    {
        $issuer = Issuer::query()->withoutTenantScope()->where('tenant_id', $tenant->getKey())->first();

        if ($issuer === null) {
            throw ValidationException::withMessages([
                'certificate' => 'Salve os dados do emitente antes de enviar o certificado.',
            ]);
        }

        return $issuer;
    }

    protected function normalizeCnpj(string $cnpj): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $cnpj) ?? '');
    }

    protected function normalizeAlphanumeric(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $value) ?? '');

        return $clean === '' ? null : $clean;
    }
}
