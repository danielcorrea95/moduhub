<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadPlatformCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin-panel') ?? false;
    }

    public function rules(): array
    {
        return [
            // Mesmo limite do emitente: um .pfx A1 com a cadeia tem poucos KB. Quem valida é a leitura do PKCS#12.
            'certificate' => 'required|file|max:64',
            // `password` e `current_password` ficam fora da sessão quando a validação falha (dontFlash padrão).
            'password' => 'required|string|max:255',
            // Sessão aberta e esquecida não basta para trocar o selo de todos os documentos.
            'current_password' => 'required|current_password',
        ];
    }

    public function messages(): array
    {
        return [
            'certificate.required' => 'Escolha o arquivo .pfx do certificado.',
            'certificate.max' => 'O arquivo é maior do que um certificado A1 costuma ser. Confira se é o .pfx correto.',
            'password.required' => 'Informe a senha do certificado.',
            'current_password.required' => 'Confirme a senha da sua conta.',
            'current_password.current_password' => 'A senha da sua conta não confere.',
        ];
    }

    public function pkcs12(): string
    {
        return (string) file_get_contents($this->file('certificate')->getRealPath());
    }

    public function password(): string
    {
        return $this->string('password')->toString();
    }
}
