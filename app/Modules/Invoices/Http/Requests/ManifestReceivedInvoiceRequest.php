<?php

namespace App\Modules\Invoices\Http\Requests;

use App\Modules\Invoices\Models\ReceivedInvoice;
use Illuminate\Foundation\Http\FormRequest;

class ManifestReceivedInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manifest', $this->route('receivedInvoice')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => $this->filled('reason') ? trim((string) $this->input('reason')) : null]);
    }

    public function rules(): array
    {
        return [
            'decision' => 'required|in:confirm,reject',
            'reason_code' => 'nullable|required_if:decision,reject|in:'.implode(',', array_keys(ReceivedInvoice::REJECTION_REASON_LABELS)),
            // xMotivo: opcional na rejeição, mas com 15 a 255 caracteres quando vem; obrigatório em "Outros".
            'reason' => 'nullable|required_if:reason_code,9|string|min:15|max:255',
        ];
    }

    public function attributes(): array
    {
        return ['decision' => 'decisão', 'reason_code' => 'motivo', 'reason' => 'justificativa'];
    }

    public function confirms(): bool
    {
        return $this->validated('decision') === 'confirm';
    }

    public function reasonCode(): ?string
    {
        return $this->validated('reason_code') !== null ? (string) $this->validated('reason_code') : null;
    }

    public function reason(): ?string
    {
        return $this->validated('reason');
    }
}
