<?php

namespace App\Modules\Invoices\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Invoices\Contracts\IssuerRepositoryContract;
use App\Modules\Invoices\Contracts\MunicipalAgreementRepositoryContract;
use App\Modules\Invoices\Http\Requests\ListMunicipalAgreementsRequest;
use App\Modules\Invoices\Http\Resources\MunicipalAgreementResource;
use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Services\Nfse\Municipalities;
use Inertia\Inertia;
use Inertia\Response;

class MunicipalAgreementController extends Controller
{
    public function __construct(
        protected MunicipalAgreementRepositoryContract $agreements,
        protected IssuerRepositoryContract $issuers,
    ) {}

    public function index(ListMunicipalAgreementsRequest $request): Response
    {
        $environment = $request->environment();
        $filters = $request->filters();
        $issuer = $this->issuers->forCurrentTenant();

        return Inertia::render('Invoices/Agreements', [
            'agreements' => $this->agreements
                ->paginate($environment, $filters, $request->perPage())
                ->through(fn ($agreement) => MunicipalAgreementResource::make($agreement)->resolve($request)),
            'counts' => $this->agreements->countBySituation($environment, $filters['uf']),
            'filters' => ['environment' => $environment, ...$filters],
            'states' => Municipalities::states(),
            'environments' => Issuer::ENVIRONMENT_LABELS,
            'lastCheckedAt' => $this->agreements->lastCheckedAt($environment)?->toIso8601String(),
            'issuerCity' => $issuer === null ? null : [
                'city_code' => $issuer->city_code,
                'label' => Municipalities::label($issuer->city_code),
                'environment' => $issuer->environment,
                'agreement' => ($found = $this->agreements->find($issuer->city_code, $issuer->environment)) === null
                    ? null
                    : MunicipalAgreementResource::make($found)->resolve($request),
            ],
        ]);
    }
}
