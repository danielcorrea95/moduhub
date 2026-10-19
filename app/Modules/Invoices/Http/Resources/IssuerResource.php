<?php

namespace App\Modules\Invoices\Http\Resources;

use App\Modules\Invoices\Models\Issuer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mesma forma no painel e na API. O .pfx e a senha nunca saem daqui: só metadados do certificado.
 *
 * @mixin Issuer
 */
class IssuerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'legal_name' => $this->legal_name,
            'trade_name' => $this->trade_name,
            'cnpj' => $this->cnpj,
            'municipal_registration' => $this->municipal_registration,
            'city_code' => $this->city_code,
            'city_name' => $this->city_name,
            'state' => $this->state,
            'simples_nacional' => $this->simples_nacional,
            'simples_regime' => $this->simples_regime,
            'special_regime' => $this->special_regime,
            'default_service' => $this->default_service,
            'default_approximate_taxes' => $this->default_approximate_taxes,
            'email_taker' => $this->email_taker,
            'danfse_stub' => $this->danfse_stub,
            'ibs_cbs' => [
                'cst' => $this->ibs_cbs_cst,
                'class_code' => $this->ibs_cbs_class_code,
                'operation_code' => $this->ibs_cbs_operation_code,
            ],
            'phone' => $this->phone,
            'email' => $this->email,
            'environment' => $this->environment,
            'dps_series' => $this->dps_series,
            'next_dps_number' => $this->next_dps_number,
            'ready_to_issue' => $this->isReadyToIssue(),
            'certificate' => $this->hasCertificate() ? [
                'subject' => $this->certificate_subject,
                'cnpj' => $this->certificate_cnpj,
                'issuer' => $this->certificate_issuer,
                'serial' => $this->certificate_serial,
                'fingerprint_sha256' => $this->certificate_fingerprint,
                'valid_from' => $this->certificate_valid_from?->toIso8601String(),
                'valid_until' => $this->certificate_valid_until?->toIso8601String(),
                'uploaded_at' => $this->certificate_uploaded_at?->toIso8601String(),
                'expired' => $this->certificateIsExpired(),
                'expires_soon' => $this->certificateExpiresWithin(30),
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
