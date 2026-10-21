<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RemovePlatformCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin-panel') ?? false;
    }

    public function rules(): array
    {
        return [
            'current_password' => 'required|current_password',
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Confirme a senha da sua conta.',
            'current_password.current_password' => 'A senha da sua conta não confere.',
        ];
    }
}
