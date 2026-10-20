<?php

namespace App\Modules\Mailing\Http\Requests;

use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;

class SendTestEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = app(CurrentTenant::class)->get();

        return $tenant !== null && $this->user()?->can('update', $tenant);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['to' => trim((string) $this->input('to'))]);
    }

    public function rules(): array
    {
        return [
            'to' => ['required', 'email', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['to' => 'destinatário'];
    }
}
