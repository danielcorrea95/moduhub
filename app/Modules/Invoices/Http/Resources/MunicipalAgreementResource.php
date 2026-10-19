<?php

namespace App\Modules\Invoices\Http\Resources;

use App\Modules\Invoices\Models\MunicipalAgreement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MunicipalAgreement */
class MunicipalAgreementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'city_code' => $this->city_code,
            'name' => $this->cityName(),
            'uf' => $this->state(),
            'environment' => $this->environment,
            'situation' => $this->situation(),
            'issues_through_sefin' => $this->allowsNationalIssuance(),
            'agreement' => [
                'status' => $this->status,
                'national_environment' => $this->national_environment,
                'national_emitter' => $this->national_emitter,
                'rfb_standard_issuance' => $this->rfb_standard_issuance,
                'national_assessment' => $this->national_assessment,
                'credit_use' => $this->credit_use,
            ],
            'message' => $this->message,
            'checked_at' => $this->checked_at?->toIso8601String(),
        ];
    }
}
