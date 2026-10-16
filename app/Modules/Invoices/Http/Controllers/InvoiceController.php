<?php

namespace App\Modules\Invoices\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Invoices\Contracts\InvoiceRepositoryContract;
use App\Modules\Invoices\Contracts\InvoiceServiceContract;
use App\Modules\Invoices\Contracts\IssuerRepositoryContract;
use App\Modules\Invoices\Http\Requests\CancelInvoiceRequest;
use App\Modules\Invoices\Http\Requests\IssueInvoiceRequest;
use App\Modules\Invoices\Http\Requests\ListInvoicesRequest;
use App\Modules\Invoices\Http\Resources\InvoiceResource;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Services\Danfse\DanfseGenerator;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceRepositoryContract $invoices,
        protected InvoiceServiceContract $service,
        protected IssuerRepositoryContract $issuers,
    ) {}

    public function index(ListInvoicesRequest $request): Response
    {
        $page = $this->invoices
            ->paginateForListing($request->status(), $request->search())
            ->through(fn (Invoice $i) => [
                'id' => $i->getKey(),
                'status' => $i->status,
                'environment' => $i->environment,
                'nfse_number' => $i->nfse_number,
                'dps_number' => $i->dps_number,
                'taker_name' => $i->taker_name,
                'taker_document' => $i->taker_document,
                'amount' => (string) $i->amount,
                'competence_date' => $i->competence_date?->toDateString(),
                'issued_at' => $i->issued_at,
                'created_at' => $i->created_at,
            ]);

        return Inertia::render('Invoices/Index', [
            'invoices' => $page,
            'statusCounts' => $this->invoices->countByStatus(),
            'filters' => [
                'status' => $request->status(),
                'search' => $request->search(),
            ],
            'issuerReady' => (bool) $this->issuers->forCurrentTenant()?->isReadyToIssue(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('issue', Invoice::class);
        $issuer = $this->issuers->forCurrentTenant();

        // "Corrigir e reenviar": parte dos dados de uma nota rejeitada; a emissão gera uma DPS nova.
        $source = is_string($request->query('from')) ? $this->invoices->findRejected($request->query('from')) : null;

        return Inertia::render('Invoices/Create', [
            'retryOf' => $source === null ? null : [
                'id' => $source->getKey(),
                'dps_number' => $source->dps_number,
                'rejection' => $source->rejection ?? [],
                'draft' => [
                    'external_id' => $source->external_id,
                    'competence_date' => $source->competence_date?->toDateString(),
                    'taker' => $source->taker,
                    'service' => $source->service,
                    'values' => [
                        'amount' => (string) $source->amount,
                        'iss_rate' => $source->iss_rate !== null ? (string) $source->iss_rate : null,
                        'iss_withheld' => $source->iss_withheld,
                        'iss_taxation' => $source->iss_taxation,
                        'approximate_taxes' => $source->approximate_taxes,
                    ],
                ],
            ],
            'issuer' => $issuer === null ? null : [
                'legal_name' => $issuer->legal_name,
                'cnpj' => $issuer->cnpj,
                'city_code' => $issuer->city_code,
                'city_name' => $issuer->city_name,
                'environment' => $issuer->environment,
                'simples_nacional' => $issuer->simples_nacional,
                'next_dps_number' => $issuer->next_dps_number,
                'ready_to_issue' => $issuer->isReadyToIssue(),
            ],
            'options' => [
                'iss_taxation' => Invoice::ISS_TAXATION_LABELS,
                'environment' => Issuer::ENVIRONMENT_LABELS,
            ],
        ]);
    }

    public function store(IssueInvoiceRequest $request, CurrentTenant $currentTenant): RedirectResponse
    {
        $invoice = $this->service->issue($currentTenant->get(), $request->user(), $request->payload());

        return redirect()
            ->route('invoices.show', $invoice)
            ->with(...$this->flashFor($invoice));
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        return Inertia::render('Invoices/Show', [
            'invoice' => InvoiceResource::make($invoice)->resolve($request),
            'canCancel' => $request->user()->can('cancel', $invoice),
            'options' => [
                'cancel_reason' => Invoice::CANCEL_REASON_LABELS,
                'iss_taxation' => Invoice::ISS_TAXATION_LABELS,
            ],
        ]);
    }

    public function sync(Invoice $invoice): RedirectResponse
    {
        $this->authorize('sync', $invoice);
        $invoice = $this->service->sync($invoice);

        return back()->with(...$this->flashFor($invoice));
    }

    public function cancel(CancelInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->service->cancel($invoice, $request->reasonCode(), $request->reason());

        return back()->with('success', 'Nota cancelada na Sefin Nacional.');
    }

    public function xml(Invoice $invoice): HttpResponse
    {
        $this->authorize('view', $invoice);
        abort_unless($invoice->hasNfseXml(), 404);

        return response($invoice->nfse_xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="nfse-'.($invoice->access_key ?? $invoice->getKey()).'.xml"',
        ]);
    }

    public function pdf(Invoice $invoice, DanfseGenerator $danfse): HttpResponse
    {
        $this->authorize('view', $invoice);
        abort_unless($invoice->hasNfseXml(), 404);

        return response($danfse->forInvoice($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$danfse->filename($invoice).'"',
        ]);
    }

    /** @return array{0: string, 1: string} */
    protected function flashFor(Invoice $invoice): array
    {
        return match ($invoice->status) {
            Invoice::STATUS_ISSUED => ['success', 'NFS-e emitida.'],
            Invoice::STATUS_REJECTED => ['error', 'A Sefin Nacional rejeitou a DPS. Veja os motivos abaixo.'],
            Invoice::STATUS_CANCELLED => ['success', 'Esta nota já está cancelada.'],
            default => ['error', 'A Sefin Nacional não respondeu. Use "Sincronizar" em alguns minutos.'],
        };
    }
}
