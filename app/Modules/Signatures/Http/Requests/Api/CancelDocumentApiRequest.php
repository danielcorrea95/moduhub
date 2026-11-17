<?php

namespace App\Modules\Signatures\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CancelDocumentApiRequest extends FormRequest
{
    /** A chave de API e o escopo já foram verificados pelos middlewares api.key e api.scope. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'nullable|string|max:500',
        ];
    }
}
