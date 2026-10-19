<?php

namespace App\Modules\Invoices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendInvoiceEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('invoice')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $emails = $this->input('emails');
        if (is_string($emails)) {
            // O painel manda os endereços num campo só, separados por vírgula ou espaço.
            $emails = preg_split('/[\s,;]+/', $emails, -1, PREG_SPLIT_NO_EMPTY);
        }

        $this->merge(['emails' => is_array($emails) ? array_values(array_unique(array_map('trim', $emails))) : []]);
    }

    public function rules(): array
    {
        return [
            'emails' => 'array|max:10',
            'emails.*' => 'email|max:254',
        ];
    }

    public function messages(): array
    {
        return [
            'emails.max' => 'Envie para no máximo 10 endereços.',
            'emails.*.email' => 'Endereço de e-mail inválido: :input.',
        ];
    }

    /** @return list<string> */
    public function emails(): array
    {
        return $this->validated('emails') ?? [];
    }
}
