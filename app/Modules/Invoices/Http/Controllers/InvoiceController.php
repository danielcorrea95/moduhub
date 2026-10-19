<?php

namespace App\Modules\Invoices\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Invoices\Contracts\DistributedDocumentRepositoryContract;
use App\Modules\Invoices\Contracts\InvoiceRepositoryContract;
use App\Modules\Invoices\Contracts\InvoiceServiceContract;
use App\Modules\Invoices\Contracts\IssuerRepositoryContract;
use App\Modules\Invoices\Contracts\MunicipalAgreementRepositoryContract;
use App\Modules\Invoices\Http\Requests\CancelInvoiceRequest;
use App\Modules\Invoices\Http\Requests\CancellationReviewRequest;
use App\Modules\Invoices\Http\Requests\CheckServiceCodeRequest;
use App\Modules\Invoices\Http\Requests\IssueInvoiceRequest;
use App\Modules\Invoices\Http\Requests\ListInvoicesRequest;
use App\Modules\Invoices\Http\Requests\ReplaceInvoiceRequest;
use App\Modules\Invoices\Http\Requests\SendInvoiceEmailRequest;
use App\Modules\Invoices\Http\Resources\InvoiceEventResource;
use App\Modules\Invoices\Http\Resources\InvoiceResource;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Services\Danfse\DanfseGenerator;
use App\Modules\Invoices\Services\InvoiceDeliveryService;
use App\Modules\Invoices\Services\InvoiceEventService;
use App\Modules\Invoices\Services\InvoiceQuotaService;
use App\Modules\Invoices\Services\Nfse\Municipalities;
use App\Modules\Invoices\Services\ServiceCodeChecker;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
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
        protected MunicipalAgreementRepositoryContract $agreements,
        protected DistributedDocumentRepositoryContract $documents,
    ) {}

    public function index(ListInvoicesRequest $request, CurrentTenant $currentTenant, InvoiceQuotaService $quota): Response
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
            'usage' => $quota->usage($currentTenant->get())->toArray(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('issue', Invoice::class);
        $issuer = $this->issuers->forCurrentTenant();

        // "Corrigir e reenviar": parte dos dados de uma nota rejeitada; a emissão gera uma DPS nova.
        $source = is_string($request->query('from')) ? $this->invoices->findRejected($request->query('from')) : null;
        // "Substituir": parte de uma nota emitida; a nova DPS leva o grupo subst apontando para ela.
        $original = is_string($request->query('replace')) ? $this->invoices->findIssued($request->query('replace')) : null;
        if ($original !== null) {
            $this->authorize('replace', $original);
        }

        return Inertia::render('Invoices/Create', [
            'retryOf' => $source === null ? null : [
                'id' => $source->getKey(),
                'dps_number' => $source->dps_number,
                'rejection' => $source->rejection ?? [],
                'draft' => $this->draft($source),
                // Rejeitada numa substituição: o reenvio continua substituindo a mesma nota.
                'replaces' => $source->isReplacement() ? $this->replaceTarget($source->replaces, $source) : null,
            ],
            'replacing' => $original === null ? null : [
                ...$this->replaceTarget($original),
                'draft' => $this->draft($original),
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
                // Só aviso: a tabela vem de uma varredura semanal e a Sefin é quem decide.
                'default_service' => $issuer->default_service,
                'email_taker' => $issuer->email_taker,
                'agreement_situation' => $this->agreements->find($issuer->city_code, $issuer->environment)?->situation(),
                'ibs_cbs' => $issuer->hasIbsCbsDefaults() ? [
                    'cst' => $issuer->ibs_cbs_cst,
                    'class_code' => $issuer->ibs_cbs_class_code,
                    'operation_code' => $issuer->ibs_cbs_operation_code,
                ] : null,
            ],
            'options' => [
                'iss_taxation' => Invoice::ISS_TAXATION_LABELS,
                'environment' => Issuer::ENVIRONMENT_LABELS,
                'substitution_reason' => Invoice::SUBSTITUTION_REASON_LABELS,
                'immunity' => Invoice::IMMUNITY_LABELS,
                'nif_absence' => Invoice::NIF_ABSENCE_LABELS,
                'provision_mode' => Invoice::PROVISION_MODE_LABELS,
                'deduction_document' => Invoice::DEDUCTION_DOCUMENT_LABELS,
                'deduction_reason' => Invoice::DEDUCTION_REASON_LABELS,
                'pis_cofins_withholding' => Invoice::PIS_COFINS_WITHHOLDING_LABELS,
                'iss_suspension' => Invoice::ISS_SUSPENSION_LABELS,
                'ibs_cbs_operation' => Invoice::IBS_CBS_OPERATION_LABELS,
                'government_entity' => Invoice::GOVERNMENT_ENTITY_LABELS,
                'reimbursement_type' => Invoice::REIMBURSEMENT_TYPE_LABELS,
                'dfe_type' => Invoice::DFE_TYPE_LABELS,
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

    public function replace(ReplaceInvoiceRequest $request, Invoice $invoice, CurrentTenant $currentTenant): RedirectResponse
    {
        $replacement = $this->service->replace(
            $invoice,
            $currentTenant->get(),
            $request->user(),
            $request->payload(),
            $request->reasonCode(),
            $request->reason(),
        );

        return redirect()
            ->route('invoices.show', $replacement)
            ->with(...$this->flashFor($replacement));
    }

    public function email(SendInvoiceEmailRequest $request, Invoice $invoice, InvoiceDeliveryService $delivery): RedirectResponse
    {
        $sent = $delivery->resend($invoice, $request->emails());

        return back()->with('success', 'Nota enviada para a fila de e-mail: '.implode(', ', $sent).'.');
    }

    public function cancellationReview(CancellationReviewRequest $request, Invoice $invoice, InvoiceEventService $events): RedirectResponse
    {
        $events->requestCancellationReview($invoice, $request->reasonCode(), $request->reason());

        return back()->with('success', 'Pedido de cancelamento enviado para análise fiscal da prefeitura.');
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        return Inertia::render('Invoices/Show', [
            'invoice' => InvoiceResource::make($invoice)->resolve($request),
            'canCancel' => $request->user()->can('cancel', $invoice),
            'canReplace' => $request->user()->can('replace', $invoice),
            'events' => $invoice->access_key === null ? [] : InvoiceEventResource::collection($this->documents->eventsFor($invoice->access_key))->resolve($request),
            'cities' => collect([
                $invoice->service['city_code'] ?? null,
                $invoice->taker['address']['city_code'] ?? null,
                $invoice->intermediary['address']['city_code'] ?? null,
            ])
                ->filter()
                ->mapWithKeys(fn (string $code) => [$code => Municipalities::label($code) ?? $code]),
            'options' => [
                'cancel_reason' => Invoice::CANCEL_REASON_LABELS,
                'substitution_reason' => Invoice::SUBSTITUTION_REASON_LABELS,
                'iss_taxation' => Invoice::ISS_TAXATION_LABELS,
                'deduction_document' => Invoice::DEDUCTION_DOCUMENT_LABELS,
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

    /** Checagem ao vivo do formulário: o código está parametrizado no município do emitente? */
    public function checkServiceCode(CheckServiceCodeRequest $request, ServiceCodeChecker $checker): JsonResponse
    {
        $issuer = $this->issuers->forCurrentTenant();
        if ($issuer === null || ! $issuer->isReadyToIssue()) {
            return response()->json(['status' => 'unknown', 'service_code' => null, 'rate' => null, 'message' => null]);
        }

        $check = $checker->check(
            $issuer,
            $request->validated('national_tax_code'),
            $request->validated('municipal_tax_code'),
            $request->validated('city_code'),
            $request->competence(),
        );

        return response()->json([
            'status' => $check->status,
            'service_code' => $check->serviceCode,
            'rate' => $check->rate,
            'message' => $check->isNotParametrized() ? $checker->notParametrizedMessage($issuer, $check, $request->competence()) : null,
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

    /** Campos da nota no formato do formulário de emissão. */
    protected function draft(Invoice $invoice): array
    {
        $values = InvoiceResource::make($invoice)->resolve()['values'];

        return [
            'external_id' => $invoice->external_id,
            'competence_date' => $invoice->competence_date?->toDateString(),
            'taker' => $invoice->taker,
            'intermediary' => $invoice->intermediary,
            'service' => $invoice->service,
            'values' => $values,
        ];
    }

    /** Nota a substituir, e o motivo já escolhido quando vem de uma substituta rejeitada. */
    protected function replaceTarget(Invoice $original, ?Invoice $rejected = null): array
    {
        return [
            'id' => $original->getKey(),
            'nfse_number' => $original->nfse_number,
            'access_key' => $original->access_key,
            'issued_at' => $original->issued_at?->toIso8601String(),
            'reason_code' => $rejected?->substitution_reason_code,
            'reason' => $rejected?->substitution_reason,
        ];
    }

    /** @return array{0: string, 1: string} */
    protected function flashFor(Invoice $invoice): array
    {
        return match ($invoice->status) {
            Invoice::STATUS_ISSUED => ['success', $invoice->isReplacement() ? 'NFS-e substituta emitida. A original ficou como substituída.' : 'NFS-e emitida.'],
            Invoice::STATUS_REJECTED => ['error', 'A Sefin Nacional rejeitou a DPS. Veja os motivos abaixo.'],
            Invoice::STATUS_CANCELLED => ['success', 'Esta nota já está cancelada.'],
            default => ['error', 'A Sefin Nacional não respondeu. Use "Sincronizar" em alguns minutos.'],
        };
    }
}
