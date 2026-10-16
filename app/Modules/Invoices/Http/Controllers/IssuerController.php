<?php

namespace App\Modules\Invoices\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Invoices\Contracts\IssuerRepositoryContract;
use App\Modules\Invoices\Contracts\IssuerServiceContract;
use App\Modules\Invoices\Http\Requests\UpdateIssuerRequest;
use App\Modules\Invoices\Http\Requests\UploadIssuerCertificateRequest;
use App\Modules\Invoices\Http\Resources\IssuerResource;
use App\Modules\Invoices\Models\Issuer;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IssuerController extends Controller
{
    public function __construct(
        protected IssuerRepositoryContract $issuers,
        protected IssuerServiceContract $service,
    ) {}

    public function edit(Request $request): Response
    {
        $this->authorize('view', Issuer::class);

        $issuer = $this->issuers->forCurrentTenant();

        return Inertia::render('Invoices/Issuer', [
            'issuer' => $issuer ? IssuerResource::make($issuer)->resolve() : null,
            'canManage' => $request->user()->can('manage', Issuer::class),
            'options' => [
                'simples_nacional' => Issuer::SIMPLES_NACIONAL_LABELS,
                'simples_regime' => Issuer::SIMPLES_REGIME_LABELS,
                'special_regime' => Issuer::SPECIAL_REGIME_LABELS,
                'environment' => Issuer::ENVIRONMENT_LABELS,
            ],
        ]);
    }

    public function update(UpdateIssuerRequest $request, CurrentTenant $tenant): RedirectResponse
    {
        $this->service->configure($tenant->get(), $request->attrs());

        $request->session()->flash('success', 'Dados do emitente salvos.');

        return back();
    }

    public function storeCertificate(UploadIssuerCertificateRequest $request, CurrentTenant $tenant): RedirectResponse
    {
        $this->service->attachCertificate($tenant->get(), $request->pkcs12(), $request->password());

        $request->session()->flash('success', 'Certificado digital salvo.');

        return back();
    }

    public function destroyCertificate(Request $request, CurrentTenant $tenant): RedirectResponse
    {
        $this->authorize('manage', Issuer::class);

        $this->service->removeCertificate($tenant->get());

        $request->session()->flash('success', 'Certificado removido.');

        return back();
    }
}
