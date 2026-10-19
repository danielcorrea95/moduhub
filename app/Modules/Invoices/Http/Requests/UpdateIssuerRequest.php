<?php

namespace App\Modules\Invoices\Http\Requests;

use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Rules\IbgeCityCode;
use App\Modules\Invoices\Rules\IbsCbsCodes;
use App\Modules\Invoices\Rules\ValidCnpj;
use App\Modules\Invoices\Services\Nfse\Municipalities;
use Illuminate\Foundation\Http\FormRequest;

class UpdateIssuerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', Issuer::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $cityCode = preg_replace('/\D/', '', (string) $this->input('city_code'));

        $this->merge([
            'city_code' => $cityCode,
            // Nome e UF saem da tabela do IBGE: digitados à mão, divergiam do código e iam errados para o DANFSe.
            'city_name' => Municipalities::name($cityCode) ?? $this->input('city_name'),
            'state' => Municipalities::uf($cityCode) ?? ($this->filled('state') ? strtoupper(trim((string) $this->input('state'))) : null),
            'cnpj' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $this->input('cnpj')) ?? ''),
            'default_service' => $this->defaultService(),
            'default_approximate_taxes' => $this->defaultTaxes(),
            'email_taker' => $this->has('email_taker') ? $this->boolean('email_taker') : true,
            'danfse_stub' => $this->boolean('danfse_stub'),
            'ibs_cbs_cst' => $this->codeInput('ibs_cbs_cst'),
            'ibs_cbs_class_code' => $this->codeInput('ibs_cbs_class_code'),
            'ibs_cbs_operation_code' => $this->codeInput('ibs_cbs_operation_code'),
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
            'city_code' => ['required', 'digits:7', new IbgeCityCode],
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
            ...IbsCbsCodes::rules('ibs_cbs_cst', 'ibs_cbs_class_code', 'ibs_cbs_operation_code', $this->input('ibs_cbs_cst')),
            'default_service' => 'nullable|array:national_tax_code,municipal_tax_code,nbs_code,description',
            'default_service.national_tax_code' => 'required_with:default_service|nullable|digits:6',
            'default_service.municipal_tax_code' => 'nullable|digits:3',
            'default_service.nbs_code' => 'nullable|digits:9',
            'default_service.description' => 'nullable|string|max:2000',
            'default_approximate_taxes' => 'nullable|array:federal,state,municipal',
            'default_approximate_taxes.federal' => 'nullable|numeric|min:0|max:100',
            'default_approximate_taxes.state' => 'nullable|numeric|min:0|max:100',
            'default_approximate_taxes.municipal' => 'nullable|numeric|min:0|max:100',
            'email_taker' => 'boolean',
            'danfse_stub' => 'boolean',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'environment' => 'required|in:'.implode(',', Issuer::ENVIRONMENTS),
            'dps_series' => ['required', 'regex:/^\d{1,5}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            ...IbsCbsCodes::messages('ibs_cbs_cst', 'ibs_cbs_class_code', 'ibs_cbs_operation_code'),
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
            'ibs_cbs_cst',
            'ibs_cbs_class_code',
            'ibs_cbs_operation_code',
            'default_service',
            'default_approximate_taxes',
            'email_taker',
            'danfse_stub',
            'phone',
            'email',
            'environment',
            'dps_series',
        ]);
    }

    public function attributes(): array
    {
        return [
            'default_service.national_tax_code' => 'código de tributação nacional padrão',
            'default_service.municipal_tax_code' => 'código de tributação municipal padrão',
            'default_service.nbs_code' => 'código NBS padrão',
            'default_service.description' => 'descrição padrão do serviço',
        ];
    }

    /** Grupo vazio (o painel manda os campos sempre) vira null: sem serviço padrão. */
    protected function defaultService(): ?array
    {
        $service = $this->input('default_service');
        if (! is_array($service)) {
            return null;
        }

        $service = [
            'national_tax_code' => $this->codeInput('default_service.national_tax_code'),
            'municipal_tax_code' => $this->codeInput('default_service.municipal_tax_code'),
            'nbs_code' => $this->codeInput('default_service.nbs_code'),
            'description' => trim((string) ($service['description'] ?? '')) ?: null,
        ];

        return array_filter($service) === [] ? null : $service;
    }

    protected function defaultTaxes(): ?array
    {
        $taxes = $this->input('default_approximate_taxes');
        if (! is_array($taxes)) {
            return null;
        }

        $taxes = array_map(fn ($v) => $v === '' || $v === null ? null : $v, array_intersect_key($taxes, array_flip(['federal', 'state', 'municipal'])));

        return array_filter($taxes, fn ($v) => $v !== null) === [] ? null : $taxes;
    }

    protected function codeInput(string $key): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $this->input($key));

        return $digits === '' ? null : $digits;
    }
}
