<?php

namespace App\Modules\Billing\Http\Requests;

class ChangePaymentMethodRequest extends BillingRequest
{
    public function rules(): array
    {
        return [
            ...$this->accountRules(),
            ...$this->cardRules(),
        ];
    }
}
