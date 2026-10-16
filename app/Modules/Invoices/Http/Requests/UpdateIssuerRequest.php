<?php

namespace App\Modules\Invoices\Http\Requests;

use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Rules\ValidCnpj;
use Illuminate\Foundation\Http\FormRequest;

class UpdateIssuerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', Issuer::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cnpj' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $this->input('cnpj')) ?? ''),
            'state' => $this->filled('state') ? strtoupper(trim((string) $this->input('state'))) : null,
            'simples_regime' => $this->filled('simples_regime') ? $this->input('simples_regime') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'legal_name' => 'required|string|max:255',
            'trade_name' => 'nullable|string|max:255',
            'cnpj' => ['required', 'string', new ValidCnpj],
            'municipal_registration' => 'nullable|string|max:15',
            'city_code' => 'required|digits:7',
            'city_name' => 'nullable|string|max:120',
            'state' => 'nullable|string|size:2|alpha',
            'simples_nacional' => 'required|integer|in:'.implode(',', array_keys(Issuer::SIMPLES_NACIONAL_LABELS)),
            'simples_regime' => [
                'nullable',
                'required_if:simples_nacional,'.Issuer::SIMPLES_ME_EPP,
                'integer',
                'in:'.implode(',', array_keys(Issuer::SIMPLES_REGIME_LABELS)),
            ],
            'special_regime' => 'required|integer|in:'.implode(',', array_keys(Issuer::SPECIAL_REGIME_LABELS)),
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'environment' => 'required|in:'.implode(',', Issuer::ENVIRONMENTS),
            'dps_series' => ['required', 'regex:/^\d{1,5}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'city_code.digits' => 'Informe o código IBGE do município com 7 dígitos.',
            'simples_regime.required_if' => 'Informe o regime de apuração do Simples Nacional.',
            'dps_series.regex' => 'A série deve ter de 1 a 5 dígitos.',
        ];
    }

    public function attrs(): array
    {
        return $this->safe()->only([
            'legal_name',
            'trade_name',
            'cnpj',
            'municipal_registration',
            'city_code',
            'city_name',
            'state',
            'simples_nacional',
            'simples_regime',
            'special_regime',
            'phone',
            'email',
            'environment',
            'dps_series',
        ]);
    }
}
