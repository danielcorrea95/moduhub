<?php

namespace App\Modules\Invoices\Http\Requests;

use App\Modules\Invoices\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class CancelInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cancel', $this->route('invoice')) ?? false;
    }

    public function rules(): array
    {
        return [
            'reason_code' => 'required|integer|in:'.implode(',', array_keys(Invoice::CANCEL_REASON_LABELS)),
            // xMotivo do evento 101101: entre 15 e 255 caracteres.
            'reason' => 'required|string|min:15|max:255',
        ];
    }

    public function attributes(): array
    {
        return [
            'reason_code' => 'motivo',
            'reason' => 'justificativa',
        ];
    }

    public function reasonCode(): int
    {
        return (int) $this->validated('reason_code');
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
