<?php

namespace App\Modules\Invoices\Http\Resources;

use App\Modules\Invoices\Models\DistributedDocument;
use App\Modules\Invoices\Services\Nfse\NfseEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DistributedDocument */
class InvoiceEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $event = NfseEvent::fromXml($this->xml);

        return [
            'nsu' => $this->nsu,
            'code' => $event->code,
            'label' => $event->label(),
            'reason_code' => $event->reasonCode,
            'reason' => $event->reason,
            'author_document' => $event->authorDocument,
            'processed_at' => ($event->processedAt ?? $this->generated_at)?->toIso8601String(),
        ];
    }
}
