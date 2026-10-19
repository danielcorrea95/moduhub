<?php

namespace App\Modules\Invoices\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Invoices\Contracts\DistributedDocumentRepositoryContract;
use App\Modules\Invoices\Contracts\InvoiceRepositoryContract;
use App\Modules\Invoices\Contracts\InvoiceServiceContract;
use App\Modules\Invoices\Http\Requests\Api\CancelInvoiceApiRequest;
use App\Modules\Invoices\Http\Requests\Api\CancellationReviewApiRequest;
use App\Modules\Invoices\Http\Requests\Api\IssueInvoiceApiRequest;
use App\Modules\Invoices\Http\Requests\Api\ListInvoicesApiRequest;
use App\Modules\Invoices\Http\Requests\Api\ReplaceInvoiceApiRequest;
use App\Modules\Invoices\Http\Requests\Api\SendInvoiceEmailApiRequest;
use App\Modules\Invoices\Http\Resources\InvoiceEventResource;
use App\Modules\Invoices\Http\Resources\InvoiceResource;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Services\Danfse\DanfseGenerator;
use App\Modules\Invoices\Services\InvoiceDeliveryService;
use App\Modules\Invoices\Services\InvoiceEventService;
use App\Modules\Invoices\Services\InvoiceQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        if (! $invoice->wasRecentlyCreated) {
            return InvoiceResource::make($invoice)->response()->setStatusCode(200);
        }

        return $this->transmitted($invoice);
    }

    /** Mesmos códigos da emissão: 201 substituta gerada (a original vira "replaced"), 422 rejeitada, 202 pendente. */
    public function replace(ReplaceInvoiceApiRequest $request, Invoice $invoice): JsonResponse
    {
        return $this->transmitted($this->service->replace(
            $invoice,
            $request->attributes->get('tenant'),
            null,
            $request->payload(),
            $request->reasonCode(),
            $request->reason(),
        ));
    }

    protected function transmitted(Invoice $invoice): JsonResponse
    {
        $resource = InvoiceResource::make($invoice)->response();

        return match ($invoice->status) {
            Invoice::STATUS_ISSUED => $resource->setStatusCode(201),
            Invoice::STATUS_REJECTED => $resource->setStatusCode(422)->setData([
                'message' => 'A Sefin Nacional rejeitou a DPS.',
                'data' => InvoiceResource::make($invoice)->resolve(request()),
            ]),
            default => $resource->setStatusCode(202),
        };
    }

    /** Consumo de notas no período da assinatura, contra a cota do plano. */
    public function usage(Request $request, InvoiceQuotaService $quota): JsonResponse
    {
        return response()->json(['data' => $quota->usage($request->attributes->get('tenant'))->toArray()]);
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

    /** 202: o e-mail vai pela fila. Sem `emails`, vai para o e-mail do tomador. */
    public function email(SendInvoiceEmailApiRequest $request, Invoice $invoice, InvoiceDeliveryService $delivery): JsonResponse
    {
        return response()->json(['data' => ['queued_to' => $delivery->resend($invoice, $request->emails())]], 202);
    }

    public function cancellationReview(CancellationReviewApiRequest $request, Invoice $invoice, InvoiceEventService $events): InvoiceResource
    {
        return InvoiceResource::make($events->requestCancellationReview($invoice, $request->reasonCode(), $request->reason()));
    }

    /** Eventos da nota distribuídos pelo ADN: cancelamentos, manifestações do tomador, decisões do fisco. */
    public function events(Request $request, Invoice $invoice, DistributedDocumentRepositoryContract $documents): JsonResponse
    {
        $events = $invoice->access_key === null ? collect() : $documents->eventsFor($invoice->access_key);

        return response()->json(['data' => InvoiceEventResource::collection($events)->resolve($request)]);
    }

    public function xml(Invoice $invoice): Response
    {
        abort_unless($invoice->hasNfseXml(), 404, 'A NFS-e ainda não foi gerada.');

        return response($invoice->nfse_xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="nfse-'.($invoice->access_key ?? $invoice->getKey()).'.xml"',
        ]);
    }

    public function pdf(Invoice $invoice, DanfseGenerator $danfse): Response
    {
        abort_unless($invoice->hasNfseXml(), 404, 'A NFS-e ainda não foi gerada.');

        return response($danfse->forInvoice($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$danfse->filename($invoice).'"',
        ]);
    }
}
