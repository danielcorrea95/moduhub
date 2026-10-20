<?php

namespace App\Modules\Billing\Http\Requests;

/** Ações sem formulário (cancelar, ver o Pix): só a autorização. */
class ManageBillingRequest extends BillingRequest
{
    public function rules(): array
    {
        return [];
    }
}
