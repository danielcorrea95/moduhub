<?php

namespace App\Modules\Invoices\Http\Requests;

use App\Modules\Invoices\Models\Invoice;

/**
 * Dados completos da NFS-e substituta (os mesmos da emissão) mais o motivo da substituição.
 */
class ReplaceInvoiceRequest extends IssueInvoiceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('replace', $this->route('invoice')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $substitution = (array) $this->input('substitution', []);
        $reason = trim((string) ($substitution['reason'] ?? ''));
        $code = (string) ($substitution['reason_code'] ?? '');

        $this->merge(['substitution' => [
            // Aceita 1 e "01": o leiaute quer dois dígitos.
            'reason_code' => ctype_digit($code) ? str_pad($code, 2, '0', STR_PAD_LEFT) : $code,
            'reason' => $reason === '' ? null : $reason,
        ]]);
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'substitution' => 'required|array:reason_code,reason',
            'substitution.reason_code' => 'required|string|in:'.implode(',', array_keys(Invoice::SUBSTITUTION_REASON_LABELS)),
            // xMotivo do grupo subst: opcional no XSD, entre 15 e 255 caracteres; em "Outros" é a única explicação.
            'substitution.reason' => 'nullable|required_if:substitution.reason_code,'.Invoice::SUBSTITUTION_OTHER.'|string|min:15|max:255',
        ];
    }

    public function attributes(): array
    {
        return [
            ...parent::attributes(),
            'substitution.reason_code' => 'motivo da substituição',
            'substitution.reason' => 'descrição do motivo',
        ];
    }

    public function messages(): array
    {
        return [
            'substitution.reason.required_if' => 'Descreva o motivo da substituição.',
        ];
    }

    /** Payload da nota, sem o grupo de substituição. */
    public function payload(): array
    {
        $data = $this->validated();
        unset($data['substitution']);

        return $data;
    }

    public function reasonCode(): string
    {
        return (string) $this->validated('substitution.reason_code');
    }

    public function reason(): ?string
    {
        return $this->validated('substitution.reason');
    }
}
