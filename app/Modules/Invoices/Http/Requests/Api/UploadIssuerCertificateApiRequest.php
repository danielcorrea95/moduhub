<?php

namespace App\Modules\Invoices\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UploadIssuerCertificateApiRequest extends FormRequest
{
    private const MAX_BYTES = 64 * 1024;

    private ?string $decoded = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'certificate' => 'required_without:certificate_base64|file|max:64',
            'certificate_base64' => 'required_without:certificate|string',
            'password' => 'required|string|max:255',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->hasFile('certificate') || ! is_string($this->input('certificate_base64'))) {
                    return;
                }

                $binary = base64_decode(preg_replace('#^data:[^;]+;base64,#', '', $this->input('certificate_base64')), true);

                if ($binary === false || $binary === '') {
                    $validator->errors()->add('certificate_base64', 'O conteúdo não é um base64 válido.');
                } elseif (strlen($binary) > self::MAX_BYTES) {
                    $validator->errors()->add('certificate_base64', 'O certificado pode ter no máximo 64 KB.');
                } else {
                    $this->decoded = $binary;
                }
            },
        ];
    }

    public function pkcs12(): string
    {
        if ($this->hasFile('certificate')) {
            return (string) file_get_contents($this->file('certificate')->getRealPath());
        }

        return (string) $this->decoded;
    }

    public function password(): string
    {
        return $this->string('password')->toString();
    }
}
