<?php

namespace App\Modules\Invoices\Http\Requests;

use App\Modules\Invoices\Models\Issuer;
use Illuminate\Foundation\Http\FormRequest;

class UploadIssuerCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', Issuer::class) ?? false;
    }

    public function rules(): array
    {
        return [
            // Um .pfx A1 com a cadeia tem poucos KB; 64 KB já é folga. O tipo não é checado pelo mime
            // porque navegadores mandam octet-stream: quem valida o conteúdo é a leitura do PKCS#12.
            'certificate' => 'required|file|max:64',
            'password' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'certificate.required' => 'Escolha o arquivo .pfx do certificado.',
            'certificate.max' => 'O arquivo é maior do que um certificado A1 costuma ser. Confira se é o .pfx correto.',
            'password.required' => 'Informe a senha do certificado.',
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
