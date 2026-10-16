<?php

namespace App\Modules\Invoices\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Invoices\Contracts\InvoiceRepositoryContract;
use App\Modules\Invoices\Contracts\InvoiceServiceContract;
use App\Modules\Invoices\Http\Requests\Api\CancelInvoiceApiRequest;
use App\Modules\Invoices\Http\Requests\Api\IssueInvoiceApiRequest;
use App\Modules\Invoices\Http\Requests\Api\ListInvoicesApiRequest;
use App\Modules\Invoices\Http\Resources\InvoiceResource;
use App\Modules\Invoices\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceRepositoryContract $invoices,
        protected InvoiceServiceContract $service,
    ) {}

    public function index(ListInvoicesApiRequest $request): AnonymousResourceCollection
    {
        return InvoiceResource::collection($this->invoices->paginateForListing(
            $request->status(),
            $request->search(),
            $request->perPage(),
            $request->validated('external_id'),
        ));
    }

    /**
     * 201 emitida, 422 rejeitada pela Sefin (com a lista em `data.rejection`), 202 sem resposta
     * da Sefin (sincronizar depois) e 200 quando o external_id já tinha nota.
     */
    public function store(IssueInvoiceApiRequest $request): JsonResponse
    {
        $invoice = $this->service->issue($request->attributes->get('tenant'), null, $request->payload());
        $resource = InvoiceResource::make($invoice)->response();

        if (! $invoice->wasRecentlyCreated) {
            return $resource->setStatusCode(200);
        }

        return match ($invoice->status) {
            Invoice::STATUS_ISSUED => $resource->setStatusCode(201),
            Invoice::STATUS_REJECTED => $resource->setStatusCode(422)->setData([
                'message' => 'A Sefin Nacional rejeitou a DPS.',
                'data' => InvoiceResource::make($invoice)->resolve($request),
            ]),
            default => $resource->setStatusCode(202),
        };
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return InvoiceResource::make($invoice);
    }

    public function sync(Invoice $invoice): InvoiceResource
    {
        return InvoiceResource::make($this->service->sync($invoice));
    }

    public function cancel(CancelInvoiceApiRequest $request, Invoice $invoice): InvoiceResource
    {
        return InvoiceResource::make($this->service->cancel($invoice, $request->reasonCode(), $request->reason()));
    }

    public function xml(Invoice $invoice): Response
    {
        abort_unless($invoice->hasNfseXml(), 404, 'A NFS-e ainda não foi gerada.');

        return response($invoice->nfse_xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="nfse-'.($invoice->access_key ?? $invoice->getKey()).'.xml"',
        ]);
    }
}
