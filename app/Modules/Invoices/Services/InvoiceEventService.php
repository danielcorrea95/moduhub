<?php

namespace App\Modules\Invoices\Services;

use App\Modules\Invoices\Contracts\IssuerRepositoryContract;
use App\Modules\Invoices\Contracts\SefinClientContract;
use App\Modules\Invoices\Models\CertificateEvent;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Models\ReceivedInvoice;
use App\Modules\Invoices\Services\Nfse\DpsBuilder;
use App\Modules\Invoices\Services\Nfse\EventRequestBuilder;
use App\Modules\Invoices\Services\Nfse\IssuerCredentialsLoader;
use App\Modules\Invoices\Services\Nfse\XmlSchemaValidator;
use App\Modules\Invoices\Services\Nfse\XmlSigner;
use App\Modules\Invoices\Services\Sefin\SefinUnavailableException;
use DOMDocument;
use DOMElement;
use Illuminate\Validation\ValidationException;

/**
 * Eventos que o próprio workspace registra além do cancelamento comum: o pedido de cancelamento
 * por análise fiscal (fora do prazo) e a manifestação sobre notas de terceiros.
 */
class InvoiceEventService
{
    /** Evento de confirmação e de rejeição para cada papel na nota. */
    private const MANIFESTATIONS = [
        ReceivedInvoice::ROLE_TAKER => [EventRequestBuilder::TAKER_CONFIRMATION, EventRequestBuilder::TAKER_REJECTION],
        ReceivedInvoice::ROLE_INTERMEDIARY => [EventRequestBuilder::INTERMEDIARY_CONFIRMATION, EventRequestBuilder::INTERMEDIARY_REJECTION],
        ReceivedInvoice::ROLE_PROVIDER => [EventRequestBuilder::PROVIDER_CONFIRMATION, EventRequestBuilder::PROVIDER_REJECTION],
    ];

    public function __construct(
        protected IssuerRepositoryContract $issuers,
        protected SefinClientContract $sefin,
        protected IssuerCredentialsLoader $credentials,
        protected EventRequestBuilder $events,
        protected XmlSigner $signer,
        protected XmlSchemaValidator $schema,
    ) {}

    /** Evento 101101 negado por prazo: o fisco municipal decide o cancelamento (evento 101103). */
    public function requestCancellationReview(Invoice $invoice, int $reasonCode, string $reason): Invoice
    {
        if (! $invoice->isIssued()) {
            throw ValidationException::withMessages(['review' => 'Só notas emitidas podem ter o cancelamento analisado pelo fisco.']);
        }
        if (($invoice->cancellation_review['status'] ?? null) === 'requested') {
            throw ValidationException::withMessages(['review' => 'Já existe um pedido de análise fiscal aguardando decisão.']);
        }

        $this->register($invoice->issuer, (string) $invoice->access_key, EventRequestBuilder::CANCELLATION_REVIEW, $reasonCode, $reason, 'review');

        $invoice->forceFill(['cancellation_review' => [
            'status' => 'requested',
            'reason_code' => (string) $reasonCode,
            'reason' => $reason,
            'requested_at' => now()->toIso8601String(),
        ]])->save();

        return $invoice;
    }

    public function manifest(ReceivedInvoice $receivedInvoice, bool $confirm, ?string $reasonCode = null, ?string $reason = null): ReceivedInvoice
    {
        if (! $receivedInvoice->isIssued()) {
            throw ValidationException::withMessages(['manifestation' => 'A nota não está mais válida: foi cancelada ou substituída.']);
        }
        if ($receivedInvoice->isManifested()) {
            throw ValidationException::withMessages(['manifestation' => 'Esta nota já tem manifestação registrada.']);
        }

        // A manifestação sai assinada pelo destinatário da nota, que é o workspace dono dela.
        $issuer = $this->issuers->forTenant($receivedInvoice->tenant_id);
        if ($issuer === null || ! $issuer->isReadyToIssue()) {
            throw ValidationException::withMessages(['manifestation' => 'A manifestação é assinada com o certificado do emitente: configure-o antes.']);
        }

        [$confirmation, $rejection] = self::MANIFESTATIONS[$receivedInvoice->role];
        $this->register($issuer, $receivedInvoice->access_key, $confirm ? $confirmation : $rejection, $confirm ? null : $reasonCode, $reason, 'manifestation');

        $receivedInvoice->forceFill(['manifestation' => array_filter([
            'type' => $confirm ? 'confirmed' : 'rejected',
            'reason_code' => $confirm ? null : $reasonCode,
            'reason' => $reason,
            'at' => now()->toIso8601String(),
        ], fn ($v) => $v !== null)])->save();

        return $receivedInvoice;
    }

    protected function register(Issuer $issuer, string $accessKey, string $event, string|int|null $reasonCode, ?string $reason, string $errorKey): void
    {
        $credentials = $this->credentials->load($issuer, CertificateEvent::PURPOSE_EVENT_SIGNATURE, ['access_key' => $accessKey, 'event' => $event]);
        $doc = $this->events->build($accessKey, $issuer, $event, $reasonCode, $reason);
        $this->signer->sign($this->signedElement($doc), $credentials);
        $this->schema->assertValid($doc, 'pedRegEvento_v1.01.xsd');

        try {
            $response = $this->sefin->registerEvent($issuer, $accessKey, $doc->saveXML());
        } catch (SefinUnavailableException) {
            throw ValidationException::withMessages([$errorKey => 'A Sefin Nacional não respondeu. Tente de novo em alguns minutos.']);
        }

        if (! $response->successful) {
            throw ValidationException::withMessages([$errorKey => 'A Sefin Nacional recusou o evento: '.$response->errorSummary()]);
        }
    }

    protected function signedElement(DOMDocument $doc): DOMElement
    {
        $element = $doc->getElementsByTagNameNS(DpsBuilder::NS, 'infPedReg')->item(0);
        if (! $element instanceof DOMElement) {
            throw new \LogicException('Elemento infPedReg ausente no pedido de evento.');
        }

        return $element;
    }
}
