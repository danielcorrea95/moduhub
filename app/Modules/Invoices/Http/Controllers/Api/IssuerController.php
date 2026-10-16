<?php

namespace App\Modules\Invoices\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Invoices\Contracts\IssuerRepositoryContract;
use App\Modules\Invoices\Contracts\IssuerServiceContract;
use App\Modules\Invoices\Http\Requests\Api\UpdateIssuerApiRequest;
use App\Modules\Invoices\Http\Requests\Api\UploadIssuerCertificateApiRequest;
use App\Modules\Invoices\Http\Resources\IssuerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IssuerController extends Controller
{
    public function __construct(
        protected IssuerRepositoryContract $issuers,
        protected IssuerServiceContract $service,
    ) {}

    public function show(): IssuerResource
    {
        $issuer = $this->issuers->forCurrentTenant();
        abort_if($issuer === null, 404, 'Nenhum emitente configurado neste workspace.');

        return IssuerResource::make($issuer);
    }

    public function update(UpdateIssuerApiRequest $request): JsonResponse
    {
        $issuer = $this->service->configure($request->attributes->get('tenant'), $request->attrs());

        return IssuerResource::make($issuer)
            ->response()
            ->setStatusCode($issuer->wasRecentlyCreated ? 201 : 200);
    }

    public function storeCertificate(UploadIssuerCertificateApiRequest $request): IssuerResource
    {
        $issuer = $this->service->attachCertificate(
            $request->attributes->get('tenant'),
            $request->pkcs12(),
            $request->password(),
        );

        return IssuerResource::make($issuer);
    }

    public function destroyCertificate(Request $request): JsonResponse
    {
        $this->service->removeCertificate($request->attributes->get('tenant'));

        return response()->json(null, 204);
    }
}
