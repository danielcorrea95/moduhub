<?php

namespace App\Modules\Signatures\Http\Requests;

use App\Modules\Signatures\Models\Document;
use App\Modules\Signatures\Models\DocumentSigner;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSignerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');
        return $document instanceof Document
            && $this->user()?->can('manageSigners', $document);
    }

    public function rules(): array
    {
        $methods = implode(',', [
            DocumentSigner::AUTH_EMAIL,
            DocumentSigner::AUTH_SMS,
            DocumentSigner::AUTH_EMAIL_AND_SMS,
        ]);

        return [
            'name' => 'sometimes|required|string|max:255',
            'role' => 'nullable|string|max:60',
            'email' => 'sometimes|required|email|max:255',
            'cpf' => 'nullable|string|max:14',
            'cnpj' => 'nullable|string|max:18',
            'phone' => 'nullable|string|max:32',
            'auth_method' => "nullable|in:{$methods}",
        ];
    }
}
