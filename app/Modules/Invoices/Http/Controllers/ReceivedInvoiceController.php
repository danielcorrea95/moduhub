<?php

namespace App\Modules\Invoices\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Invoices\Contracts\DistributedDocumentRepositoryContract;
use App\Modules\Invoices\Contracts\IssuerRepositoryContract;
use App\Modules\Invoices\Contracts\ReceivedInvoiceRepositoryContract;
use App\Modules\Invoices\Http\Requests\ListReceivedInvoicesRequest;
use App\Modules\Invoices\Http\Requests\ManifestReceivedInvoiceRequest;
use App\Modules\Invoices\Http\Resources\InvoiceEventResource;
use App\Modules\Invoices\Http\Resources\ReceivedInvoiceResource;
use App\Modules\Invoices\Models\ReceivedInvoice;
use App\Modules\Invoices\Services\Danfse\DanfseGenerator;
use App\Modules\Invoices\Services\DocumentSyncService;
use App\Modules\Invoices\Services\InvoiceEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReceivedInvoiceController extends Controller
{
    public function __construct(
        protected ReceivedInvoiceRepositoryContract $received,
        protected IssuerRepositoryContract $issuers,
    ) {}

    public function index(ListReceivedInvoicesRequest $request, DistributedDocumentRepositoryContract $documents): Response
    {
        $issuer = $this->issuers->forCurrentTenant();

        return Inertia::render('Invoices/Received', [
            'invoices' => $this->received
                ->paginateForListing($request->role(), $request->status(), $request->search(), $request->perPage())
                ->through(fn (ReceivedInvoice $invoice) => [
                    ...ReceivedInvoiceResource::make($invoice)->resolve($request),
                    'events' => InvoiceEventResource::collection($documents->eventsFor($invoice->access_key))->resolve($request),
                ]),
            'roleCounts' => $this->received->countByRole(),
            'filters' => ['role' => $request->role(), 'status' => $request->status(), 'search' => $request->search()],
            'syncedAt' => $issuer?->dfe_synced_at?->toIso8601String(),
            'canSync' => (bool) $issuer?->isReadyToIssue(),
            'canManifest' => $request->user()->can('manifestAny', ReceivedInvoice::class),
            'options' => [
                'role' => ReceivedInvoice::ROLE_LABELS,
                'rejection_reason' => ReceivedInvoice::REJECTION_REASON_LABELS,
            ],
        ]);
    }

    public function sync(Request $request, DocumentSyncService $sync): RedirectResponse
    {
        $this->authorize('viewAny', ReceivedInvoice::class);
        $issuer = $this->issuers->forCurrentTenant();
        abort_unless($issuer?->isReadyToIssue(), 422, 'Configure o emitente com certificado válido.');

        $totals = $sync->sync($issuer);

        return $totals['error'] !== null
            ? back()->with('error', 'O ADN não respondeu: '.$totals['error'])
            : back()->with('success', "Sincronizado: {$totals['received']} notas recebidas e {$totals['events']} eventos novos.");
    }

    public function manifest(ManifestReceivedInvoiceRequest $request, ReceivedInvoice $receivedInvoice, InvoiceEventService $events): RedirectResponse
    {
        $events->manifest($receivedInvoice, $request->confirms(), $request->reasonCode(), $request->reason());

        return back()->with('success', $request->confirms() ? 'Nota confirmada na Sefin Nacional.' : 'Nota rejeitada na Sefin Nacional.');
    }

    public function xml(ReceivedInvoice $receivedInvoice): HttpResponse
    {
        $this->authorize('view', $receivedInvoice);

        return response($receivedInvoice->nfse_xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="nfse-'.$receivedInvoice->access_key.'.xml"',
        ]);
    }

    public function pdf(ReceivedInvoice $receivedInvoice, DanfseGenerator $danfse): HttpResponse
    {
        $this->authorize('view', $receivedInvoice);

        return response($danfse->forReceived($receivedInvoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="danfse-'.$receivedInvoice->access_key.'.pdf"',
        ]);
    }
}
