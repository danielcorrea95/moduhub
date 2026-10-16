<?php

namespace App\Modules\Signatures\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyAndSignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|size:6',
            'accepted' => 'required|accepted',
            'signature_image' => ['nullable', 'string', 'regex:/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/', 'max:200000'],
        ];
    }

    public function messages(): array
    {
        return [
            'signature_image.regex' => 'Formato de assinatura inválido.',
            'signature_image.max' => 'A imagem da assinatura é muito grande.',
        ];
    }
}
