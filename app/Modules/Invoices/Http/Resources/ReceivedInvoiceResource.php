<?php

namespace App\Modules\Invoices\Http\Resources;

use App\Modules\Invoices\Models\ReceivedInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ReceivedInvoice */
class ReceivedInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'environment' => $this->environment,
            'access_key' => $this->access_key,
            'role' => $this->role,
            'status' => $this->status,
            'nfse_number' => $this->nfse_number,
            'provider' => ['document' => $this->provider_document, 'name' => $this->provider_name],
            'taker' => ['document' => $this->taker_document, 'name' => $this->taker_name],
            'competence_date' => $this->competence_date?->toDateString(),
            'issued_at' => $this->issued_at?->toIso8601String(),
            'amount' => $this->amount !== null ? (string) $this->amount : null,
            'iss_amount' => $this->iss_amount !== null ? (string) $this->iss_amount : null,
            'description' => $this->description,
            'manifestation' => $this->manifestation,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
