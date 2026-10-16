<?php

namespace App\Modules\Signatures\Http\Requests;

use App\Modules\Signatures\Models\Document;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Document::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'file' => 'required|file|mimetypes:application/pdf|max:25600',
            'message' => 'nullable|string|max:1000',
            'sign_order' => 'nullable|in:parallel,sequential',
            'expires_at' => 'nullable|date|after:now',
        ];
    }

    public function attrs(): array
    {
        return [
            'name' => $this->input('name'),
            'message' => $this->input('message'),
            'sign_order' => $this->input('sign_order', Document::SIGN_ORDER_PARALLEL),
            'expires_at' => $this->input('expires_at'),
        ];
    }
}
