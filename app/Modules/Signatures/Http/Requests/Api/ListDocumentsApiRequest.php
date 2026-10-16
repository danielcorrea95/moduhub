<?php

namespace App\Modules\Signatures\Http\Requests\Api;

use App\Modules\Signatures\Models\Document;
use Illuminate\Foundation\Http\FormRequest;

class ListDocumentsApiRequest extends FormRequest
{
    /** A chave de API e o escopo já foram verificados pelos middlewares api.key e api.scope. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|in:'.implode(',', array_keys(Document::LISTING_FILTERS)),
            'search' => 'nullable|string|max:120',
            'external_id' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
