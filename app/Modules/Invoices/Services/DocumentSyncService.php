<?php

namespace App\Modules\Invoices\Services;

use App\Modules\Invoices\Contracts\DfeDistributionContract;
use App\Modules\Invoices\Contracts\InvoiceRepositoryContract;
use App\Modules\Invoices\Contracts\ReceivedInvoiceRepositoryContract;
use App\Modules\Invoices\Models\DistributedDocument;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Models\ReceivedInvoice;
use App\Modules\Invoices\Services\Adn\DistributedDfe;
use App\Modules\Invoices\Services\Nfse\NfseDocument;
use App\Modules\Invoices\Services\Nfse\NfseEvent;
use App\Platform\Contracts\WebhookDispatcherContract;
use Illuminate\Support\Facades\DB;

/**
 * Consome a distribuição do ADN para um emitente: arquiva cada documento, cria as notas recebidas
 * e aplica nas nossas notas os eventos registrados por terceiros (tomador, fisco, prefeitura).
 *
 * Roda em comando agendado, sem sessão: toda busca é feita pelo tenant do emitente, explicitamente.
 */
class DocumentSyncService
{
    /** Proteção contra fila enorme numa execução só; a próxima continua do NSU salvo. */
    private const MAX_BATCHES = 40;

    public function __construct(
        protected DfeDistributionContract $adn,
        protected InvoiceRepositoryContract $invoices,
        protected ReceivedInvoiceRepositoryContract $received,
        protected InvoiceDeliveryService $delivery,
        protected WebhookDispatcherContract $webhooks,
    ) {}

    /** @return array{documents: int, received: int, events: int, error: string|null} */
    public function sync(Issuer $issuer): array
    {
        $environment = $issuer->environment;
        $cursor = (int) (($issuer->dfe_cursor ?? [])[$environment] ?? 0);
        $totals = ['documents' => 0, 'received' => 0, 'events' => 0, 'error' => null];

        for ($i = 0; $i < self::MAX_BATCHES; $i++) {
            $batch = $this->adn->fetch($issuer, $cursor);

            if ($batch->error !== null) {
                $totals['error'] = $batch->error;
                break;
            }

            foreach ($batch->documents as $document) {
                DB::transaction(function () use ($issuer, $document, &$totals) {
                    if (! $this->archive($issuer, $document)) {
                        return;
                    }
                    $totals['documents']++;

                    match ($document->type) {
                        DistributedDocument::TYPE_NFSE => $this->receiveNfse($issuer, $document) && $totals['received']++,
                        DistributedDocument::TYPE_EVENT => $this->applyEvent($issuer, $document) && $totals['events']++,
                        default => null,
                    };
                });
                $cursor = max($cursor, $document->nsu);
            }

            $this->saveCursor($issuer, $cursor);

            if ($batch->exhausted || $batch->documents === []) {
                break;
            }
        }

        $issuer->forceFill(['dfe_synced_at' => now()])->save();

        return $totals;
    }

    /** Guarda o documento; false se o NSU já tinha chegado (execução repetida). */
    protected function archive(Issuer $issuer, DistributedDfe $document): bool
    {
        $existing = DistributedDocument::query()
            ->withoutTenantScope()
            ->where('issuer_id', $issuer->getKey())
            ->where('environment', $issuer->environment)
            ->where('nsu', $document->nsu)
            ->exists();

        if ($existing) {
            return false;
        }

        DistributedDocument::query()->create([
            'tenant_id' => $issuer->tenant_id,
            'issuer_id' => $issuer->getKey(),
            'environment' => $issuer->environment,
            'nsu' => $document->nsu,
            'access_key' => $document->accessKey,
            'document_type' => $document->type,
            'event_type' => $document->eventType,
            'xml' => $document->xml,
            'generated_at' => $document->generatedAt,
        ]);

        return true;
    }

    /** NFS-e de terceiro em que o emitente aparece. As nossas já estão em invoices. */
    protected function receiveNfse(Issuer $issuer, DistributedDfe $document): bool
    {
        if ($this->invoices->findByAccessKeyForTenant($issuer->tenant_id, $document->accessKey) !== null) {
            return false;
        }

        $nfse = NfseDocument::fromXml($document->xml);
        $role = $this->roleOf($issuer, $nfse);
        if ($role === null) {
            return false;
        }

        $receivedInvoice = $this->received->findForTenant($issuer->tenant_id, $issuer->environment, $document->accessKey)
            ?? new ReceivedInvoice(['tenant_id' => $issuer->tenant_id, 'environment' => $issuer->environment, 'access_key' => $document->accessKey]);
        $isNew = ! $receivedInvoice->exists;

        $receivedInvoice->fill([
            'role' => $role,
            'status' => $receivedInvoice->status ?? ReceivedInvoice::STATUS_ISSUED,
            'provider_document' => $nfse->providerDocument,
            'provider_name' => $nfse->providerName,
            'taker_document' => $nfse->takerDocument,
            'taker_name' => $nfse->takerName,
            'nfse_number' => $nfse->number,
            'competence_date' => $nfse->competence,
            'issued_at' => $nfse->processedAt,
            'amount' => $nfse->amount,
            'iss_amount' => $nfse->issAmount,
            'description' => $nfse->description === null ? null : mb_substr($nfse->description, 0, 2000),
            'nfse_xml' => $document->xml,
        ]);
        $receivedInvoice->tenant_id = $issuer->tenant_id;
        $receivedInvoice->save();

        if ($isNew) {
            $this->fire($issuer, 'invoice.received', [
                'received_invoice_id' => $receivedInvoice->getKey(),
                'role' => $role,
                'access_key' => $receivedInvoice->access_key,
                'nfse_number' => $receivedInvoice->nfse_number,
                'provider_document' => $receivedInvoice->provider_document,
                'provider_name' => $receivedInvoice->provider_name,
                'amount' => $receivedInvoice->amount !== null ? (string) $receivedInvoice->amount : null,
            ]);
        }

        return true;
    }

    protected function roleOf(Issuer $issuer, NfseDocument $nfse): ?string
    {
        $cnpj = $issuer->cnpj;
        $candidates = [
            ReceivedInvoice::ROLE_TAKER => $nfse->takerDocument,
            ReceivedInvoice::ROLE_INTERMEDIARY => $nfse->intermediaryDocument,
            ReceivedInvoice::ROLE_PROVIDER => $nfse->providerDocument,
        ];

        foreach ($candidates as $role => $document) {
            if ($document === $cnpj) {
                return $role;
            }
        }

        // A distribuição é por raiz de CNPJ: nota de outra filial da empresa também chega aqui.
        foreach ($candidates as $role => $document) {
            if ($document !== null && strlen($document) === 14 && substr($document, 0, 8) === substr($cnpj, 0, 8)) {
                return $role;
            }
        }

        return null;
    }

    protected function applyEvent(Issuer $issuer, DistributedDfe $document): bool
    {
        $event = NfseEvent::fromXml($document->xml);
        if ($event->code === null) {
            return false;
        }

        $invoice = $this->invoices->findByAccessKeyForTenant($issuer->tenant_id, $document->accessKey);
        if ($invoice !== null) {
            $this->applyToInvoice($issuer, $invoice, $event, $document->xml);

            return true;
        }

        $receivedInvoice = $this->received->findForTenant($issuer->tenant_id, $issuer->environment, $document->accessKey);
        if ($receivedInvoice !== null) {
            $this->applyToReceived($receivedInvoice, $event);

            return true;
        }

        return false;
    }

    protected function applyToInvoice(Issuer $issuer, Invoice $invoice, NfseEvent $event, string $xml): void
    {
        $at = ($event->processedAt ?? now())->toIso8601String();

        switch ($event->code) {
            case '101101':
            case '105104':
            case '305101':
                if ($invoice->isCancelled()) {
                    break;
                }
                $review = $invoice->cancellation_review;
                if ($event->code === '105104' && is_array($review)) {
                    $invoice->cancellation_review = [...$review, 'status' => 'approved', 'decided_at' => $at];
                }
                // Cancelada fora do Moduhub (emissor web, análise fiscal ou ofício): espelha aqui.
                $invoice->forceFill([
                    'status' => Invoice::STATUS_CANCELLED,
                    'cancel_reason_code' => $event->reasonCode !== null && ctype_digit($event->reasonCode) ? (int) $event->reasonCode : $invoice->cancel_reason_code,
                    'cancel_reason' => $event->reason ?? $event->label(),
                    'cancelled_at' => $event->processedAt ?? now(),
                    'cancellation_xml' => $xml,
                ])->save();
                $this->fire($issuer, 'invoice.cancelled', $this->invoicePayload($invoice, [
                    'cancel_reason_code' => $invoice->cancel_reason_code,
                    'cancel_reason' => $invoice->cancel_reason,
                    'cancelled_at' => $invoice->cancelled_at?->toIso8601String(),
                    'origin' => $event->label(),
                ]));
                $this->delivery->afterCancelled($invoice);
                break;

            case '105102':
                if ($invoice->isIssued()) {
                    $invoice->forceFill(['status' => Invoice::STATUS_REPLACED, 'replaced_at' => $event->processedAt ?? now()])->save();
                }
                break;

            case '101103':
                $invoice->forceFill(['cancellation_review' => [
                    ...((array) $invoice->cancellation_review),
                    'status' => 'requested',
                    'reason_code' => $event->reasonCode,
                    'reason' => $event->reason,
                    'requested_at' => $at,
                ]])->save();
                break;

            case '105105':
                $invoice->forceFill(['cancellation_review' => [...((array) $invoice->cancellation_review), 'status' => 'denied', 'decided_at' => $at]])->save();
                $this->fire($issuer, 'invoice.cancellation_denied', $this->invoicePayload($invoice, ['decided_at' => $at]));
                break;

            case '305102':
                $invoice->forceFill(['blocked_at' => $event->processedAt ?? now()])->save();
                break;

            case '305103':
                $invoice->forceFill(['blocked_at' => null])->save();
                break;

            case '203202':
            case '204203':
            case '205204':
                $invoice->forceFill(['taker_manifestation' => [
                    'type' => 'confirmed',
                    'by' => ['203202' => 'taker', '204203' => 'intermediary', '205204' => 'tacit'][$event->code],
                    'at' => $at,
                ]])->save();
                break;

            case '203206':
            case '204207':
                $invoice->forceFill(['taker_manifestation' => [
                    'type' => 'rejected',
                    'by' => $event->code === '203206' ? 'taker' : 'intermediary',
                    'reason_code' => $event->reasonCode,
                    'reason' => $event->reason,
                    'at' => $at,
                ]])->save();
                $this->fire($issuer, 'invoice.taker_rejected', $this->invoicePayload($invoice, [
                    'rejected_by' => $invoice->taker_manifestation['by'],
                    'reason_code' => $event->reasonCode,
                    'reason' => $event->reason,
                    'rejected_at' => $at,
                ]));
                break;

            case '205208':
                $invoice->forceFill(['taker_manifestation' => [...((array) $invoice->taker_manifestation), 'type' => 'rejection_annulled', 'annulled_at' => $at]])->save();
                break;
        }
    }

    protected function applyToReceived(ReceivedInvoice $receivedInvoice, NfseEvent $event): void
    {
        $at = ($event->processedAt ?? now())->toIso8601String();

        match ($event->code) {
            '101101', '105104', '305101' => $receivedInvoice->forceFill(['status' => ReceivedInvoice::STATUS_CANCELLED])->save(),
            '105102' => $receivedInvoice->forceFill(['status' => ReceivedInvoice::STATUS_REPLACED])->save(),
            // A confirmação tácita vale quando o prazo de manifestação passa sem resposta.
            '205204' => $receivedInvoice->isManifested() || $receivedInvoice->forceFill(['manifestation' => ['type' => 'confirmed', 'tacit' => true, 'at' => $at]])->save(),
            default => null,
        };
    }

    protected function saveCursor(Issuer $issuer, int $cursor): void
    {
        $issuer->forceFill(['dfe_cursor' => [...((array) $issuer->dfe_cursor), $issuer->environment => $cursor]])->save();
    }

    protected function invoicePayload(Invoice $invoice, array $data): array
    {
        return array_merge([
            'invoice_id' => $invoice->getKey(),
            'external_id' => $invoice->external_id,
            'status' => $invoice->status,
            'environment' => $invoice->environment,
            'access_key' => $invoice->access_key,
            'nfse_number' => $invoice->nfse_number,
        ], $data);
    }

    protected function fire(Issuer $issuer, string $event, array $data): void
    {
        $tenant = $issuer->tenant()->withoutGlobalScopes()->first();
        if ($tenant !== null) {
            $this->webhooks->fire($tenant, $event, $data);
        }
    }
}
