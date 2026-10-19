<?php

namespace App\Modules\Invoices\Http\Resources;

use App\Modules\Invoices\Models\CertificateEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CertificateEvent
 */
class CertificateEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'type' => $this->type,
            'type_label' => CertificateEvent::TYPE_LABELS[$this->type] ?? $this->type,
            'purpose' => $this->purpose,
            'purpose_label' => $this->purpose !== null ? (CertificateEvent::PURPOSE_LABELS[$this->purpose] ?? $this->purpose) : null,
            'certificate_fingerprint' => $this->certificate_fingerprint,
            'actor' => [
                'type' => $this->actor_type,
                'label' => $this->actor_label,
            ],
            'ip_address' => $this->ip_address,
            'data' => $this->data,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
