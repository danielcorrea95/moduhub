<?php

namespace App\Modules\Invoices\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Invoices\Contracts\MunicipalAgreementRepositoryContract;
use App\Modules\Invoices\Http\Requests\Api\ListMunicipalAgreementsApiRequest;
use App\Modules\Invoices\Http\Resources\MunicipalAgreementResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MunicipalAgreementController extends Controller
{
    public function __construct(protected MunicipalAgreementRepositoryContract $agreements) {}

    public function index(ListMunicipalAgreementsApiRequest $request): AnonymousResourceCollection
    {
        return MunicipalAgreementResource::collection(
            $this->agreements->paginate($request->environment(), $request->filters(), $request->perPage()),
        )->additional(['meta' => ['last_checked_at' => $this->agreements->lastCheckedAt($request->environment())?->toIso8601String()]]);
    }

    public function show(ListMunicipalAgreementsApiRequest $request, string $cityCode): MunicipalAgreementResource
    {
        $agreement = $this->agreements->find($cityCode, $request->environment());
        abort_if($agreement === null, 404, 'Município sem convênio registrado. Confira o código IBGE.');

        return MunicipalAgreementResource::make($agreement);
    }
}
