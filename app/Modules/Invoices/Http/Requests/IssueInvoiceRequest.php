<?php

namespace App\Modules\Invoices\Http\Requests;

use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Rules\ValidTaxDocument;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Dados de uma NFS-e, iguais no painel e na API. Os limites de tamanho seguem o XSD v1.01.
 */
class IssueInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('issue', Invoice::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $taker = (array) $this->input('taker', []);
        $service = (array) $this->input('service', []);
        $values = (array) $this->input('values', []);

        $taker['document'] = $this->digits($taker['document'] ?? null, alphanumeric: true);
        $taker['phone'] = $this->digits($taker['phone'] ?? null);
        $taker['municipal_registration'] = $this->digits($taker['municipal_registration'] ?? null, alphanumeric: true);

        $address = $taker['address'] ?? null;
        if (is_array($address)) {
            $address['postal_code'] = $this->digits($address['postal_code'] ?? null);
            // O painel manda o grupo de endereço sempre; vazio, ele simplesmente não vai para a DPS.
            $filled = array_filter($address, fn ($v) => $v !== null && $v !== '');
            $taker['address'] = $filled === [] ? null : $address;
        }

        // Código de tributação nacional costuma vir com máscara (01.07.01).
        $service['national_tax_code'] = $this->digits($service['national_tax_code'] ?? null);
        $service['municipal_tax_code'] = $this->digits($service['municipal_tax_code'] ?? null);
        $service['nbs_code'] = $this->digits($service['nbs_code'] ?? null);
        $service['city_code'] = $this->digits($service['city_code'] ?? null);

        $values['iss_rate'] = ($values['iss_rate'] ?? '') === '' ? null : $values['iss_rate'];
        if (isset($values['approximate_taxes']) && is_array($values['approximate_taxes'])) {
            $values['approximate_taxes'] = array_map(fn ($v) => $v === '' ? null : $v, $values['approximate_taxes']);
        }

        $this->merge([
            'taker' => $taker,
            'service' => $service,
            'values' => $values,
            'external_id' => $this->filled('external_id') ? trim((string) $this->input('external_id')) : null,
            'competence_date' => $this->filled('competence_date') ? $this->input('competence_date') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'external_id' => 'nullable|string|max:255',
            'competence_date' => 'nullable|date_format:Y-m-d|before_or_equal:today',

            'taker' => 'required|array:document,name,email,phone,municipal_registration,address',
            'taker.document' => ['required', 'string', new ValidTaxDocument],
            'taker.name' => 'required|string|max:300',
            'taker.email' => 'nullable|email|max:80',
            'taker.phone' => 'nullable|string|min:6|max:20',
            'taker.municipal_registration' => 'nullable|string|max:15',
            'taker.address' => 'nullable|array:postal_code,street,number,complement,district,city_code',
            'taker.address.postal_code' => 'required_with:taker.address|nullable|digits:8',
            'taker.address.street' => 'required_with:taker.address|nullable|string|max:255',
            'taker.address.number' => 'required_with:taker.address|nullable|string|max:60',
            'taker.address.complement' => 'nullable|string|max:156',
            'taker.address.district' => 'required_with:taker.address|nullable|string|max:60',
            'taker.address.city_code' => 'required_with:taker.address|nullable|digits:7',

            'service' => 'required|array:national_tax_code,municipal_tax_code,nbs_code,description,city_code',
            'service.national_tax_code' => 'required|digits:6',
            'service.municipal_tax_code' => 'nullable|digits:3',
            'service.nbs_code' => 'nullable|digits:9',
            'service.description' => 'required|string|max:2000',
            'service.city_code' => 'nullable|digits:7',

            'values' => 'required|array:amount,iss_rate,iss_withheld,iss_taxation,approximate_taxes',
            'values.amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'values.iss_rate' => 'nullable|numeric|min:0|max:5',
            'values.iss_withheld' => 'nullable|boolean',
            'values.iss_taxation' => 'nullable|integer|in:'.implode(',', array_keys(Invoice::ISS_TAXATION_LABELS)),
            'values.approximate_taxes' => 'nullable|array:federal,state,municipal',
            'values.approximate_taxes.federal' => 'nullable|numeric|min:0|max:100',
            'values.approximate_taxes.state' => 'nullable|numeric|min:0|max:100',
            'values.approximate_taxes.municipal' => 'nullable|numeric|min:0|max:100',
        ];
    }

    public function attributes(): array
    {
        return [
            'competence_date' => 'data de competência',
            'taker.document' => 'CPF/CNPJ do tomador',
            'taker.name' => 'nome do tomador',
            'taker.email' => 'e-mail do tomador',
            'taker.phone' => 'telefone do tomador',
            'taker.address.postal_code' => 'CEP',
            'taker.address.street' => 'logradouro',
            'taker.address.number' => 'número',
            'taker.address.district' => 'bairro',
            'taker.address.city_code' => 'código IBGE do município do tomador',
            'service.national_tax_code' => 'código de tributação nacional',
            'service.municipal_tax_code' => 'código de tributação municipal',
            'service.nbs_code' => 'código NBS',
            'service.description' => 'descrição do serviço',
            'service.city_code' => 'município da prestação',
            'values.amount' => 'valor do serviço',
            'values.iss_rate' => 'alíquota do ISS',
        ];
    }

    /** Payload validado no formato que o InvoiceService espera. */
    public function payload(): array
    {
        return $this->validated();
    }

    protected function digits(mixed $value, bool $alphanumeric = false): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = strtoupper(preg_replace($alphanumeric ? '/[^A-Za-z0-9]/' : '/\D/', '', (string) $value) ?? '');

        return $clean === '' ? null : $clean;
    }
}
