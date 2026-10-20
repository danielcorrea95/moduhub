<?php

namespace App\Modules\Billing\Http\Requests;

use Illuminate\Validation\Rule;

class ChangePlanRequest extends BillingRequest
{
    public function rules(): array
    {
        return [
            // Carrinho: um plano por produto. A regra de um por produto fica no PlanCart.
            'plan_ids' => ['required', 'array', 'min:1', 'max:10'],
            'plan_ids.*' => ['integer', 'distinct', Rule::exists('plans', 'id')->where('is_active', true)->where('is_public', true)],
        ];
    }
}
