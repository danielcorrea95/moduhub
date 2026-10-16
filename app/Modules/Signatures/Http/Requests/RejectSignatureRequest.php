<?php

namespace App\Modules\Signatures\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectSignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:1000',
        ];
    }
}
