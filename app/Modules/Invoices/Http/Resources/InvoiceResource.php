<?php

namespace App\Modules\Invoices\Http\Resources;

use App\Modules\Invoices\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'external_id' => $this->external_id,
            'status' => $this->status,
            'environment' => $this->environment,
            'dps' => [
                'id' => $this->dps_id,
                'series' => $this->dps_series,
                'number' => $this->dps_number,
                'emitted_at' => $this->emitted_at?->toIso8601String(),
            ],
            'competence_date' => $this->competence_date?->toDateString(),
            'access_key' => $this->access_key,
            'nfse_number' => $this->nfse_number,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'taker' => $this->taker,
            'intermediary' => $this->intermediary,
            'service' => $this->service,
            'values' => [
                'amount' => (string) $this->amount,
                'intermediary_received' => $this->intermediary_received !== null ? (string) $this->intermediary_received : null,
                'discounts' => $this->unconditional_discount === null && $this->conditional_discount === null ? null : [
                    'unconditional' => $this->unconditional_discount !== null ? (string) $this->unconditional_discount : null,
                    'conditional' => $this->conditional_discount !== null ? (string) $this->conditional_discount : null,
                ],
                'deduction' => $this->deduction_amount === null && $this->deduction_percent === null && empty($this->deduction_documents) ? null : [
                    'percent' => $this->deduction_percent !== null ? (string) $this->deduction_percent : null,
                    'amount' => $this->deduction_amount !== null ? (string) $this->deduction_amount : null,
                    'documents' => $this->deduction_documents,
                ],
                'iss_rate' => $this->iss_rate !== null ? (string) $this->iss_rate : null,
                'iss_withheld' => $this->iss_withheld,
                'iss_withholder' => $this->iss_withholder,
                'iss_taxation' => $this->iss_taxation,
                'immunity_type' => $this->immunity_type,
                'federal_taxes' => $this->federal_taxes,
                'municipal_benefit' => $this->municipal_benefit,
                'iss_suspension' => $this->iss_suspension,
                'approximate_taxes' => $this->approximate_taxes,
                'ibs_cbs' => $this->ibs_cbs,
            ],
            'rejection' => $this->rejection,
            'last_error' => $this->last_error,
            'last_attempt_at' => $this->last_attempt_at?->toIso8601String(),
            'cancellation' => $this->cancelled_at === null ? null : [
                'reason_code' => $this->cancel_reason_code,
                'reason' => $this->cancel_reason,
                'cancelled_at' => $this->cancelled_at->toIso8601String(),
            ],
            'substitution' => $this->replaces_invoice_id === null ? null : [
                'replaces_invoice_id' => $this->replaces_invoice_id,
                'replaces_access_key' => $this->replaces?->access_key,
                'reason_code' => $this->substitution_reason_code,
                'reason' => $this->substitution_reason,
            ],
            'replacement' => $this->replaced_at === null ? null : [
                'invoice_id' => $this->replacedBy()?->getKey(),
                'access_key' => $this->replacedBy()?->access_key,
                'replaced_at' => $this->replaced_at->toIso8601String(),
            ],
            'delivery' => [
                'notify_taker' => $this->notify_taker,
                'emailed_at' => $this->taker_emailed_at?->toIso8601String(),
                'last_error' => $this->taker_email_error,
            ],
            // Situação vinda de eventos de terceiros, lidos na distribuição do ADN.
            'cancellation_review' => $this->cancellation_review,
            'taker_manifestation' => $this->taker_manifestation,
            'blocked_at' => $this->blocked_at?->toIso8601String(),
            'is_overage' => $this->is_overage,
            'has_xml' => $this->hasNfseXml(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
