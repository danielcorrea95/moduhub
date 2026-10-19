<?php

namespace App\Modules\Invoices\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Invoices\Contracts\InvoiceRepositoryContract;
use App\Modules\Invoices\Contracts\InvoiceServiceContract;
use App\Modules\Invoices\Contracts\IssuerRepositoryContract;
use App\Modules\Invoices\Contracts\SefinClientContract;
use App\Modules\Invoices\Models\CertificateEvent;
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
use App\Tenancy\CurrentTenant;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService implements InvoiceServiceContract
{
    private const IBS_CBS_EXTRAS = ['operation_type', 'government_entity', 'referenced_invoices', 'recipient', 'property', 'reimbursements'];

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
        protected ServiceCodeChecker $serviceCodes,
        protected InvoiceDeliveryService $delivery,
        protected InvoiceQuotaService $quota,
    ) {}

    public function issue(Tenant $tenant, ?User $creator, array $data): Invoice
    {
        $externalId = $data['external_id'] ?? null;
        if ($externalId !== null && ($existing = $this->invoices->findReusableByExternalId($tenant->getKey(), $externalId)) !== null) {
            // Reenvio do mesmo pedido (retry do CRM após timeout): devolve a nota que já existe em vez de emitir outra.
            return $existing;
        }

        return $this->createAndTransmit($tenant, $creator, $data);
    }

    public function replace(Invoice $original, Tenant $tenant, ?User $creator, array $data, string $reasonCode, ?string $reason): Invoice
    {
        if (! $original->isIssued()) {
            throw ValidationException::withMessages([
                'substitution' => 'Só notas emitidas podem ser substituídas.',
            ]);
        }

        if ($this->invoices->findPendingReplacement($original) !== null) {
            throw ValidationException::withMessages([
                'substitution' => 'Já existe uma substituta desta nota aguardando a Sefin. Sincronize-a antes de tentar de novo.',
            ]);
        }

        return $this->createAndTransmit($tenant, $creator, $data, [
            'replaces_invoice_id' => $original->getKey(),
            'substitution_reason_code' => $reasonCode,
            'substitution_reason' => $reason,
        ]);
    }

    /** @param  array<string, mixed>  $substitution  colunas do grupo subst quando a nota substitui outra */
    protected function createAndTransmit(Tenant $tenant, ?User $creator, array $data, array $substitution = []): Invoice
    {
        // Emissão também roda fora de request (API em lote, jobs): tudo daqui em diante vale para este tenant.
        return app(CurrentTenant::class)->run($tenant, fn () => $this->createAndTransmitWithinTenant($tenant, $creator, $data, $substitution));
    }

    /** @param  array<string, mixed>  $substitution */
    protected function createAndTransmitWithinTenant(Tenant $tenant, ?User $creator, array $data, array $substitution): Invoice
    {
        $issuer = $this->readyIssuer($tenant);
        $data = $this->withIssuerDefaults($data, $issuer);
        $credentials = $this->credentials->load($issuer, CertificateEvent::PURPOSE_DPS_SIGNATURE, array_filter([
            'external_id' => $data['external_id'] ?? null,
            'replaces_invoice_id' => $substitution['replaces_invoice_id'] ?? null,
        ]));
        // Antes de reservar o número: código sem parametrização no município seria rejeitado com E0312.
        $this->serviceCodes->assertIssuable($issuer, $data['service'], Carbon::parse($data['competence_date'] ?? now()->toDateString()));

        $invoice = DB::transaction(function () use ($tenant, $creator, $data, $issuer, $credentials, $substitution) {
            $locked = $this->issuers->lockForNumbering($issuer);
            // Depois do lock: emissões simultâneas do mesmo workspace contam uma de cada vez.
            $overage = $this->quota->reserve($tenant);
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
                'ibs_cbs' => $this->ibsCbs($data['values']['ibs_cbs'] ?? null, $locked),
                'is_overage' => $overage,
                ...$substitution,
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
        $credentials = $this->credentials->load($issuer, CertificateEvent::PURPOSE_CANCELLATION_SIGNATURE, [
            'invoice_id' => $invoice->getKey(),
            'access_key' => $invoice->access_key,
        ]);

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
        $this->delivery->afterCancelled($invoice);

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

        $original = DB::transaction(function () use ($invoice, $nfse, $nfseXml, $accessKey) {
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

            // A Sefin cancela a original por substituição (e105102) ao gerar a substituta; aqui só espelhamos.
            $original = $invoice->replaces;
            if ($original !== null && $original->isIssued()) {
                $original->forceFill(['status' => Invoice::STATUS_REPLACED, 'replaced_at' => now()])->save();

                return $original;
            }

            return null;
        });

        $this->fire($invoice, 'invoice.issued', [
            'issued_at' => $invoice->issued_at?->toIso8601String(),
            'replaces_invoice_id' => $invoice->replaces_invoice_id,
        ]);
        $this->delivery->afterIssued($invoice);

        if ($original !== null) {
            $this->fire($original, 'invoice.replaced', [
                'replaced_at' => $original->replaced_at?->toIso8601String(),
                'replaced_by_invoice_id' => $invoice->getKey(),
                'replaced_by_access_key' => $invoice->access_key,
                'substitution_reason_code' => $invoice->substitution_reason_code,
                'substitution_reason' => $invoice->substitution_reason,
            ]);
            $this->delivery->afterCancelled($original);
        }

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

    /**
     * Completa a nota com o que o emitente configurou como padrão: o serviço que presta quase sempre,
     * os tributos aproximados e o envio por e-mail. O que a nota informa sempre prevalece.
     */
    protected function withIssuerDefaults(array $data, Issuer $issuer): array
    {
        $service = (array) ($data['service'] ?? []);
        $default = (array) ($issuer->default_service ?? []);

        if (empty($service['national_tax_code'])) {
            if (empty($default['national_tax_code'])) {
                throw ValidationException::withMessages([
                    'service.national_tax_code' => 'Informe o código de tributação nacional ou configure o serviço padrão do emitente.',
                ]);
            }

            // Os códigos andam juntos: herdar só o nacional com o municipal de outro serviço daria E0312.
            foreach (['national_tax_code', 'municipal_tax_code', 'nbs_code'] as $key) {
                $service[$key] = $default[$key] ?? null;
            }
        }

        if (empty($service['description'])) {
            if (empty($default['description'])) {
                throw ValidationException::withMessages([
                    'service.description' => 'Informe a descrição do serviço ou configure uma descrição padrão no emitente.',
                ]);
            }
            $service['description'] = $default['description'];
        }

        $data['service'] = $service;
        $data['values']['approximate_taxes'] ??= $issuer->default_approximate_taxes;
        $data['notify_taker'] ??= $issuer->email_taker;

        return $data;
    }

    protected function readyIssuer(Tenant $tenant): Issuer
    {
        $issuer = $this->issuers->forTenant($tenant->getKey());

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
        $withheld = (bool) ($values['iss_withheld'] ?? false);

        return [
            'external_id' => $data['external_id'] ?? null,
            'competence_date' => $data['competence_date'] ?? now()->toDateString(),
            // Estrangeiro não tem CPF/CNPJ: a busca por documento usa o NIF.
            'taker_document' => $taker['document'] ?? $taker['nif'] ?? null,
            'taker_name' => $taker['name'],
            'taker' => $taker,
            'intermediary' => $data['intermediary'] ?? null,
            'service' => $data['service'],
            'amount' => $values['amount'],
            'intermediary_received' => $values['intermediary_received'] ?? null,
            'unconditional_discount' => $values['discounts']['unconditional'] ?? null,
            'conditional_discount' => $values['discounts']['conditional'] ?? null,
            'deduction_amount' => $values['deduction']['amount'] ?? null,
            'deduction_percent' => $values['deduction']['percent'] ?? null,
            'deduction_documents' => $values['deduction']['documents'] ?? null,
            'iss_rate' => $values['iss_rate'] ?? null,
            'iss_withheld' => $withheld,
            'iss_withholder' => $withheld ? ($values['iss_withholder'] ?? Invoice::WITHHOLDER_TAKER) : null,
            'iss_taxation' => (int) ($values['iss_taxation'] ?? Invoice::ISS_TAXABLE),
            'immunity_type' => isset($values['immunity_type']) ? (int) $values['immunity_type'] : null,
            'federal_taxes' => $this->federalTaxes($values['federal_taxes'] ?? null, (string) $values['amount']),
            'municipal_benefit' => $values['municipal_benefit'] ?? null,
            'iss_suspension' => $values['iss_suspension'] ?? null,
            'approximate_taxes' => $values['approximate_taxes'] ?? null,
            'notify_taker' => (bool) ($data['notify_taker'] ?? false),
        ];
    }

    /**
     * Resolve os valores dos tributos federais: quem integra pode mandar só as alíquotas, e os valores
     * saem daqui (base do PIS/COFINS e das retenções = valor do serviço, salvo base informada).
     */
    protected function federalTaxes(?array $taxes, string $amount): ?array
    {
        if ($taxes === null) {
            return null;
        }

        $of = fn (string|float|null $base, string|float|null $rate) => $rate === null ? null : number_format(round((float) $base * (float) $rate / 100, 2), 2, '.', '');
        $resolved = [];

        if (! empty($taxes['pis_cofins'])) {
            $pc = $taxes['pis_cofins'];
            $base = $pc['base'] ?? (($pc['pis_rate'] ?? null) !== null || ($pc['cofins_rate'] ?? null) !== null ? $amount : null);
            $resolved['pis_cofins'] = [
                'cst' => $pc['cst'],
                'base' => $base,
                'pis_rate' => $pc['pis_rate'] ?? null,
                'cofins_rate' => $pc['cofins_rate'] ?? null,
                'pis_amount' => $pc['pis_amount'] ?? $of($base, $pc['pis_rate'] ?? null),
                'cofins_amount' => $pc['cofins_amount'] ?? $of($base, $pc['cofins_rate'] ?? null),
                'withholding' => $pc['withholding'] ?? null,
            ];
        }

        foreach (['irrf', 'csll', 'inss'] as $tax) {
            if (! empty($taxes[$tax])) {
                $resolved[$tax] = [
                    'rate' => $taxes[$tax]['rate'] ?? null,
                    'amount' => $taxes[$tax]['amount'] ?? $of($amount, $taxes[$tax]['rate'] ?? null),
                ];
            }
        }

        return $resolved === [] ? null : $resolved;
    }

    /**
     * Códigos do IBS/CBS da nota: os informados nela ou os padrão do emitente. Sem nenhum dos dois, a DPS sai sem o grupo.
     *
     * @return array{cst: string, class_code: string, operation_code: string, final_consumer: bool|null}|null
     */
    protected function ibsCbs(?array $informed, Issuer $issuer): ?array
    {
        $codes = match (true) {
            ! empty($informed['cst']) => [
                'cst' => $informed['cst'],
                'class_code' => $informed['class_code'],
                'operation_code' => $informed['operation_code'],
            ],
            $issuer->hasIbsCbsDefaults() => [
                'cst' => $issuer->ibs_cbs_cst,
                'class_code' => $issuer->ibs_cbs_class_code,
                'operation_code' => $issuer->ibs_cbs_operation_code,
            ],
            default => null,
        };

        // Destinatário, imóvel, reembolsos e compras governamentais só existem dentro do grupo IBSCBS.
        $extras = array_filter(array_intersect_key((array) $informed, array_flip(self::IBS_CBS_EXTRAS)), fn ($v) => $v !== null);

        if ($codes === null) {
            if ($extras !== []) {
                throw ValidationException::withMessages([
                    'values.ibs_cbs.cst' => 'Os dados complementares do IBS/CBS exigem CST, cClassTrib e cIndOp, na nota ou no padrão do emitente.',
                ]);
            }

            return null;
        }

        $finalConsumer = $informed['final_consumer'] ?? null;

        return $codes + ['final_consumer' => $finalConsumer === null ? null : (bool) $finalConsumer] + $extras;
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
