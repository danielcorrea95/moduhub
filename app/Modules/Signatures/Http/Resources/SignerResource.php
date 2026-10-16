<?php

namespace App\Modules\Signatures\Http\Resources;

use App\Modules\Signatures\Contracts\SignerServiceContract;
use App\Modules\Signatures\Models\DocumentSigner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DocumentSigner */
class SignerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'order' => $this->order,
            'name' => $this->name,
            'role' => $this->role,
            'email' => $this->email,
            'cpf' => $this->cpf,
            'cnpj' => $this->cnpj,
            'phone' => $this->phone,
            'auth_method' => $this->auth_method,
            'status' => $this->status,
            // O link carrega o token de acesso do signatário: só sai enquanto ele pode assinar.
            'sign_url' => app(SignerServiceContract::class)->isAwaitingSignature($this->resource)
                ? route('public-sign.show', $this->token)
                : null,
            'viewed_at' => $this->viewed_at?->toIso8601String(),
            'signed_at' => $this->signed_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
        ];
    }
}
