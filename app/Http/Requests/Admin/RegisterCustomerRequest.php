<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Cliente cadastrado em /admin/clientes/novo: workspace, dono e planos da cortesia. */
class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin-panel') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'owner_email' => mb_strtolower(trim((string) $this->input('owner_email'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'workspace_name' => ['required', 'string', 'max:120'],
            'tax_id' => ['nullable', 'string', 'max:20'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'string', 'email', 'max:255'],
            'plan_ids' => ['required', 'array', 'min:1'],
            'plan_ids.*' => ['integer'],
        ];
    }

    public function attributes(): array
    {
        return [
            'workspace_name' => 'nome do workspace',
            'tax_id' => 'CNPJ ou CPF',
            'owner_name' => 'nome do proprietário',
            'owner_email' => 'e-mail do proprietário',
            'plan_ids' => 'planos',
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
