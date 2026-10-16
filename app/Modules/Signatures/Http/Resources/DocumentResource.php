<?php

namespace App\Modules\Signatures\Http\Resources;

use App\Modules\Signatures\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Document */
class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'status' => $this->status,
            'sign_order' => $this->sign_order,
            'message' => $this->message,
            'external_id' => $this->external_id,
            'metadata' => $this->metadata,
            'page_count' => $this->page_count,
            'file_size' => $this->file_size,
            'original_sha256' => $this->file_hash,
            'signed_sha256' => $this->final_file_hash,
            'signers_count' => $this->signers_count ?? ($this->relationLoaded('signers') ? $this->signers->count() : null),
            'signers' => SignerResource::collection($this->whenLoaded('signers')),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'downloads' => [
                'original' => route('api.signatures.documents.download', ['document' => $this->getKey(), 'type' => 'original']),
                'signed' => $this->final_file_path !== null
                    ? route('api.signatures.documents.download', ['document' => $this->getKey(), 'type' => 'signed'])
                    : null,
            ],
        ];
    }
}
