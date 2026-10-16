<?php

namespace App\Modules\Invoices\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Invoices\Contracts\InvoiceRepositoryContract;
use App\Modules\Invoices\Contracts\InvoiceServiceContract;
use App\Modules\Invoices\Contracts\IssuerRepositoryContract;
use App\Modules\Invoices\Contracts\SefinClientContract;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Services\Nfse\CancellationBuilder;
use App\Modules\Invoices\Services\Nfse\DpsBuilder;
use App\Modules\Invoices\Services\Nfse\IssuerCredentialsLoader;
use App\Modules\Invoices\Services\Nfse\NfseDocument;
use App\Modules\Invoices\Services\Nfse\XmlSchemaValidator;
use App\Modules\Invoices\Services\Nfse\XmlSigner;
use App\Modules\Invoices\Services\Sefin\SefinResponse;
use App\Modules\Invoices\Services\Sefin\SefinUnavailableException;
use App\Platform\Contracts\WebhookDispatcherContract;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService implements InvoiceServiceContract
{
    public function __construct(
        protected InvoiceRepositoryContract $invoices,
        protected IssuerRepositoryContract $issuers,
        protected SefinClientContract $sefin,
        protected IssuerCredentialsLoader $credentials,
        protected DpsBuilder $dps,
        protected CancellationBuilder $cancellation,
        protected XmlSigner $signer,
        protected XmlSchemaValidator $schema,
        protected WebhookDispatcherContract $webhooks,
    ) {}

    public function issue(Tenant $tenant, ?User $creator, array $data): Invoice
    {
        $externalId = $data['external_id'] ?? null;
        if ($externalId !== null && ($existing = $this->invoices->findReusableByExternalId($externalId)) !== null) {
            // Reenvio do mesmo pedido (retry do CRM após timeout): devolve a nota que já existe em vez de emitir outra.
            return $existing;
        }

        $issuer = $this->readyIssuer();
        $credentials = $this->credentials->load($issuer);

        $invoice = DB::transaction(function () use ($tenant, $creator, $data, $issuer, $credentials) {
            $locked = $this->issuers->lockForNumbering($issuer);
            $number = $locked->next_dps_number;
            $locked->forceFill(['next_dps_number' => $number + 1])->save();

            $invoice = new Invoice($this->attributes($data));
            $invoice->forceFill([
                'tenant_id' => $tenant->getKey(),
                'issuer_id' => $locked->getKey(),
                'creator_id' => $creator?->getKey(),
                'status' => Invoice::STATUS_PENDING,
                'environment' => $locked->environment,
                'dps_series' => $locked->dps_series,
                'dps_number' => $number,
                'dps_id' => DpsBuilder::dpsId($locked, $locked->dps_series, $number),
                // Um minuto de folga: a Sefin rejeita dhEmi à frente do relógio dela.
                'emitted_at' => now()->subMinute(),
            ]);

            $doc = $this->dps->build($invoice, $locked);
            $this->signer->sign($this->signedElement($doc, 'infDPS'), $credentials);
            // Se o XML não passa no schema, a exceção desfaz a transação e o número da DPS não é consumido.
            $this->schema->assertValid($doc, 'DPS_v1.01.xsd');

            $invoice->dps_xml = $doc->saveXML();
            $invoice->save();

            return $invoice;
        });

        return $this->transmit($invoice, $issuer);
    }

    public function sync(Invoice $invoice): Invoice
    {
        if (! $invoice->isPending()) {
            throw ValidationException::withMessages([
                'invoice' => 'Só notas aguardando resposta da Sefin podem ser sincronizadas.',
            ]);
        }

        $issuer = $invoice->issuer;

        try {
            $accessKey = $this->sefin->findAccessKeyByDps($issuer, $invoice->dps_id);
        } catch (SefinUnavailableException $e) {
            return $this->recordAttemptFailure($invoice, $e);
        }

        if ($accessKey === null) {
            // A Sefin não recebeu a DPS: reenviar o mesmo XML assinado mantém o número reservado.
            return $this->transmit($invoice, $issuer);
        }

        try {
            $response = $this->sefin->fetch($issuer, $accessKey);
        } catch (SefinUnavailableException $e) {
            return $this->recordAttemptFailure($invoice, $e);
        }

        if (! $response->successful || $response->xml === null) {
            $invoice->forceFill(['last_error' => $response->errorSummary(), 'last_attempt_at' => now()])->save();

            return $invoice;
        }

        return $this->markIssued($invoice, $response->xml, $accessKey);
    }

    public function cancel(Invoice $invoice, int $reasonCode, string $reason): Invoice
    {
        if (! $invoice->isIssued()) {
            throw ValidationException::withMessages([
                'cancel' => 'Só notas emitidas podem ser canceladas.',
            ]);
        }

        $issuer = $invoice->issuer;
        $credentials = $this->credentials->load($issuer);

        $doc = $this->cancellation->build($invoice, $issuer, $reasonCode, $reason);
        $this->signer->sign($this->signedElement($doc, 'infPedReg'), $credentials);
        $this->schema->assertValid($doc, 'pedRegEvento_v1.01.xsd');
        $signed = $doc->saveXML();

        try {
            $response = $this->sefin->registerEvent($issuer, $invoice->access_key, $signed);
        } catch (SefinUnavailableException $e) {
            throw ValidationException::withMessages([
                'cancel' => 'A Sefin Nacional não respondeu. A nota continua emitida; tente cancelar de novo em alguns minutos.',
            ]);
        }

        if (! $response->successful) {
            throw ValidationException::withMessages([
                'cancel' => 'A Sefin Nacional recusou o cancelamento: '.$response->errorSummary(),
            ]);
        }

        $invoice->forceFill([
            'status' => Invoice::STATUS_CANCELLED,
            'cancel_reason_code' => $reasonCode,
            'cancel_reason' => $reason,
            'cancelled_at' => now(),
            'cancellation_xml' => $response->xml ?? $signed,
        ])->save();

        $this->fire($invoice, 'invoice.cancelled', [
            'cancel_reason_code' => $reasonCode,
            'cancel_reason' => $reason,
            'cancelled_at' => $invoice->cancelled_at?->toIso8601String(),
        ]);

        return $invoice;
    }

    protected function transmit(Invoice $invoice, Issuer $issuer): Invoice
    {
        try {
            $response = $this->sefin->emit($issuer, $invoice->dps_xml);
        } catch (SefinUnavailableException $e) {
            return $this->recordAttemptFailure($invoice, $e);
        }

        if ($response->successful && $response->xml !== null) {
            return $this->markIssued($invoice, $response->xml, $response->accessKey);
        }

        return $this->markRejected($invoice, $response);
    }

    protected function markIssued(Invoice $invoice, string $nfseXml, ?string $accessKey): Invoice
    {
        $nfse = NfseDocument::fromXml($nfseXml);

        $invoice->forceFill([
            'status' => Invoice::STATUS_ISSUED,
            'access_key' => $accessKey ?? $nfse->accessKey,
            'nfse_number' => $nfse->number,
            'issued_at' => $nfse->processedAt ?? now(),
            'nfse_xml' => $nfseXml,
            'rejection' => null,
            'last_error' => null,
            'last_attempt_at' => now(),
        ])->save();

        $this->fire($invoice, 'invoice.issued', [
            'issued_at' => $invoice->issued_at?->toIso8601String(),
        ]);

        return $invoice;
    }

    protected function markRejected(Invoice $invoice, SefinResponse $response): Invoice
    {
        $invoice->forceFill([
            'status' => Invoice::STATUS_REJECTED,
            'rejection' => $response->errors,
            'last_error' => null,
            'last_attempt_at' => now(),
        ])->save();

        $this->fire($invoice, 'invoice.rejected', ['errors' => $response->errors]);

        return $invoice;
    }

    protected function recordAttemptFailure(Invoice $invoice, SefinUnavailableException $e): Invoice
    {
        $invoice->forceFill(['last_error' => $e->getMessage(), 'last_attempt_at' => now()])->save();

        return $invoice;
    }

    protected function readyIssuer(): Issuer
    {
        $issuer = $this->issuers->forCurrentTenant();

        if ($issuer === null) {
            throw ValidationException::withMessages(['issuer' => 'Configure o emitente antes de emitir notas.']);
        }

        if (! $issuer->hasCertificate()) {
            throw ValidationException::withMessages(['issuer' => 'Envie o certificado digital (e-CNPJ A1) do emitente antes de emitir notas.']);
        }

        if ($issuer->certificateIsExpired()) {
            throw ValidationException::withMessages(['issuer' => 'O certificado digital do emitente venceu. Envie um certificado válido.']);
        }

        return $issuer;
    }

    /** @return array<string, mixed> atributos preenchíveis da nota a partir do payload validado */
    protected function attributes(array $data): array
    {
        $taker = $data['taker'];
        $values = $data['values'];

        return [
            'external_id' => $data['external_id'] ?? null,
            'competence_date' => $data['competence_date'] ?? now()->toDateString(),
            'taker_document' => $taker['document'] ?? null,
            'taker_name' => $taker['name'],
            'taker' => $taker,
            'service' => $data['service'],
            'amount' => $values['amount'],
            'iss_rate' => $values['iss_rate'] ?? null,
            'iss_withheld' => (bool) ($values['iss_withheld'] ?? false),
            'iss_taxation' => (int) ($values['iss_taxation'] ?? Invoice::ISS_TAXABLE),
            'approximate_taxes' => $values['approximate_taxes'] ?? null,
        ];
    }

    protected function signedElement(DOMDocument $doc, string $name): DOMElement
    {
        $element = $doc->getElementsByTagNameNS(DpsBuilder::NS, $name)->item(0);

        if (! $element instanceof DOMElement) {
            throw new \LogicException("Elemento {$name} ausente no XML montado.");
        }

        return $element;
    }

    protected function fire(Invoice $invoice, string $event, array $data): void
    {
        $tenant = $invoice->tenant()->withoutGlobalScopes()->first();
        if ($tenant === null) {
            return;
        }

        $this->webhooks->fire($tenant, $event, array_merge([
            'invoice_id' => $invoice->getKey(),
            'external_id' => $invoice->external_id,
            'status' => $invoice->status,
            'environment' => $invoice->environment,
            'dps_id' => $invoice->dps_id,
            'access_key' => $invoice->access_key,
            'nfse_number' => $invoice->nfse_number,
            'amount' => (string) $invoice->amount,
        ], $data));
    }
}
