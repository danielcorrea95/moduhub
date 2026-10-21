<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RemovePlatformCertificateRequest;
use App\Http\Requests\Admin\UploadPlatformCertificateRequest;
use App\Modules\Signatures\Contracts\PlatformCertificateRepositoryContract;
use App\Modules\Signatures\Models\PlatformCertificate;
use App\Modules\Signatures\Services\PlatformCertificateService;
use App\Platform\Security\CertificateExpiry;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlatformCertificateController extends Controller
{
    public function __construct(
        protected PlatformCertificateRepositoryContract $certificates,
        protected PlatformCertificateService $service,
    ) {}

    public function index(): Response
    {
        $current = $this->certificates->current();

        return Inertia::render('Admin/PlatformCertificate', [
            'current' => $current === null ? null : self::present($current),
            'hasServerCertificate' => $this->service->hasServerCertificate(),
            'platformCnpj' => $this->service->platformCnpj(),
            'history' => $this->certificates->history(30)->map(fn (PlatformCertificate $c) => [
                ...self::present($c),
                'retired_at' => $c->retired_at?->toIso8601String(),
                'retired_reason' => $c->retired_reason,
                'retired_by' => $c->retired_by_label,
                'retired_ip' => $c->retired_ip,
            ])->values(),
        ]);
    }

    public function store(UploadPlatformCertificateRequest $request): RedirectResponse
    {
        $this->service->install($request->pkcs12(), $request->password());

        return redirect()->route('admin.platform-certificate.index')->with('success', 'Certificado da plataforma instalado. Os próximos documentos já saem com ele.');
    }

    public function destroy(RemovePlatformCertificateRequest $request): RedirectResponse
    {
        $this->service->remove();

        return redirect()->route('admin.platform-certificate.index')->with('success', 'Certificado removido do painel.');
    }

    /** Nunca inclui o .pfx nem a senha. */
    private static function present(PlatformCertificate $certificate): array
    {
        return [
            'id' => $certificate->getKey(),
            'subject' => $certificate->subject,
            'cnpj' => $certificate->cnpj,
            'issuer' => $certificate->issuer,
            'serial' => $certificate->serial,
            'fingerprint' => $certificate->fingerprint,
            'valid_from' => $certificate->valid_from->toIso8601String(),
            'valid_until' => $certificate->valid_until->toIso8601String(),
            'days_left' => CertificateExpiry::daysLeft($certificate->valid_until),
            'uploaded_at' => $certificate->created_at?->toIso8601String(),
            'uploaded_by' => $certificate->uploaded_by_label,
            'uploaded_ip' => $certificate->uploaded_ip,
        ];
    }
}
