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
            'service' => $this->service,
            'values' => [
                'amount' => (string) $this->amount,
                'iss_rate' => $this->iss_rate !== null ? (string) $this->iss_rate : null,
                'iss_withheld' => $this->iss_withheld,
                'iss_taxation' => $this->iss_taxation,
                'approximate_taxes' => $this->approximate_taxes,
            ],
            'rejection' => $this->rejection,
            'last_error' => $this->last_error,
            'last_attempt_at' => $this->last_attempt_at?->toIso8601String(),
            'cancellation' => $this->cancelled_at === null ? null : [
                'reason_code' => $this->cancel_reason_code,
                'reason' => $this->cancel_reason,
                'cancelled_at' => $this->cancelled_at->toIso8601String(),
            ],
            'has_xml' => $this->hasNfseXml(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
