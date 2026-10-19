<?php

namespace App\Modules\Invoices\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Invoices\Contracts\DistributedDocumentRepositoryContract;
use App\Modules\Invoices\Contracts\IssuerRepositoryContract;
use App\Modules\Invoices\Contracts\ReceivedInvoiceRepositoryContract;
use App\Modules\Invoices\Http\Requests\Api\ListReceivedInvoicesApiRequest;
use App\Modules\Invoices\Http\Requests\Api\ManifestReceivedInvoiceApiRequest;
use App\Modules\Invoices\Http\Resources\InvoiceEventResource;
use App\Modules\Invoices\Http\Resources\ReceivedInvoiceResource;
use App\Modules\Invoices\Models\ReceivedInvoice;
use App\Modules\Invoices\Services\Danfse\DanfseGenerator;
use App\Modules\Invoices\Services\DocumentSyncService;
use App\Modules\Invoices\Services\InvoiceEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ReceivedInvoiceController extends Controller
{
    public function __construct(protected ReceivedInvoiceRepositoryContract $received) {}

    public function index(ListReceivedInvoicesApiRequest $request): AnonymousResourceCollection
    {
        return ReceivedInvoiceResource::collection(
            $this->received->paginateForListing($request->role(), $request->status(), $request->search(), $request->perPage()),
        );
    }

    public function show(Request $request, ReceivedInvoice $receivedInvoice, DistributedDocumentRepositoryContract $documents): JsonResponse
    {
        return ReceivedInvoiceResource::make($receivedInvoice)
            ->additional(['events' => InvoiceEventResource::collection($documents->eventsFor($receivedInvoice->access_key))->resolve($request)])
            ->response();
    }

    public function manifest(ManifestReceivedInvoiceApiRequest $request, ReceivedInvoice $receivedInvoice, InvoiceEventService $events): ReceivedInvoiceResource
    {
        return ReceivedInvoiceResource::make($events->manifest($receivedInvoice, $request->confirms(), $request->reasonCode(), $request->reason()));
    }

    public function xml(ReceivedInvoice $receivedInvoice): Response
    {
        return response($receivedInvoice->nfse_xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="nfse-'.$receivedInvoice->access_key.'.xml"',
        ]);
    }

    public function pdf(ReceivedInvoice $receivedInvoice, DanfseGenerator $danfse): Response
    {
        return response($danfse->forReceived($receivedInvoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="danfse-'.$receivedInvoice->access_key.'.pdf"',
        ]);
    }

    /** Busca agora no ADN o que a varredura de hora em hora ainda não trouxe. */
    public function sync(IssuerRepositoryContract $issuers, DocumentSyncService $sync): JsonResponse
    {
        $issuer = $issuers->forCurrentTenant();
        abort_unless($issuer?->isReadyToIssue(), 422, 'Configure o emitente com certificado válido.');

        $totals = $sync->sync($issuer);

        return response()->json(['data' => $totals], $totals['error'] === null ? 200 : 502);
    }
}
