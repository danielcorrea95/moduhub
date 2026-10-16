<?php

namespace App\Modules\Signatures\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SignWithIcpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'certificate' => ['required', 'file', 'max:5120', 'mimetypes:application/x-pkcs12,application/pkcs12,application/octet-stream'],
            'password' => ['required', 'string', 'min:1', 'max:255'],
            'accepted' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'certificate.required' => 'Envie o seu certificado A1 (.pfx).',
            'certificate.file' => 'Arquivo de certificado inválido.',
            'certificate.max' => 'O certificado é muito grande (máx 5 MB).',
            'certificate.mimetypes' => 'O arquivo não parece ser um certificado .pfx.',
            'password.required' => 'Informe a senha do certificado.',
            'accepted.accepted' => 'Você precisa concordar antes de assinar.',
        ];
    }
}
