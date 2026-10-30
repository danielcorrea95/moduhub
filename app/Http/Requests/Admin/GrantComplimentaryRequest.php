<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Dá a cortesia a um workspace ou troca os planos de quem já tem. */
class GrantComplimentaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin-panel') ?? false;
    }

    public function rules(): array
    {
        return [
            'plan_ids' => ['required', 'array', 'min:1'],
            'plan_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'plan_ids.required' => 'Escolha pelo menos um plano.',
            'plan_ids.min' => 'Escolha pelo menos um plano.',
        ];
    }
}
