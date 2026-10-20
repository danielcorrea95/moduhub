<?php

namespace App\Http\Requests\Admin;

use App\Models\Module;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Plano criado ou editado em /admin/planos. Valores em reais ("24,90") viram centavos aqui. */
class SavePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin-panel') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $cents = function (string $key): ?int {
            $value = trim((string) $this->input($key, ''));
            if ($value === '') {
                return null;
            }
            // "1.234,56" ou "1234.56" ou "24,90".
            $normalized = str_contains($value, ',') ? str_replace(['.', ','], ['', '.'], $value) : $value;

            return is_numeric($normalized) ? (int) round(((float) $normalized) * 100) : -1;
        };
        $int = fn (string $key) => ($v = trim((string) $this->input($key, ''))) === '' ? null : (ctype_digit(str_replace('.', '', $v)) ? (int) str_replace('.', '', $v) : -1);

        $this->merge([
            'price_cents' => $cents('price'),
            'overage_cents' => $cents('overage'),
            'included_quota' => $int('included_quota'),
            'daily_quota' => $int('daily_quota'),
            'is_active' => $this->boolean('is_active'),
            'is_public' => $this->boolean('is_public'),
        ]);
    }

    public function rules(): array
    {
        return [
            'product' => ['required', Rule::in([Module::SIGNATURES, Module::INVOICES, Module::MAILING])],
            'name' => ['required', 'string', 'max:60'],
            'key' => ['nullable', 'string', 'max:60', 'alpha_dash'],
            'description' => ['nullable', 'string', 'max:255'],
            'price_cents' => ['required', 'integer', 'min:0', 'max:10000000'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:90'],
            'included_quota' => ['nullable', 'integer', 'min:1'],
            'daily_quota' => ['nullable', 'integer', 'min:1'],
            'overage_cents' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['boolean'],
            'is_public' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'product' => 'produto',
            'price_cents' => 'preço',
            'trial_days' => 'dias de teste grátis',
            'included_quota' => 'cota mensal',
            'daily_quota' => 'cota diária',
            'overage_cents' => 'preço do excedente',
        ];
    }

    public function messages(): array
    {
        return [
            'price_cents.integer' => 'Informe o preço em reais, como 24,90.',
            'overage_cents.integer' => 'Informe o excedente em reais, como 0,70.',
            'included_quota.integer' => 'Informe a cota em número inteiro.',
            'daily_quota.integer' => 'Informe a cota diária em número inteiro.',
        ];
    }

}
