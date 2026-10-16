<?php

namespace App\Modules\Signatures\Http\Requests;

use App\Modules\Signatures\Models\Document;
use Illuminate\Foundation\Http\FormRequest;

class ListDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Document::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|in:'.implode(',', array_keys(Document::LISTING_FILTERS)),
            'search' => 'nullable|string|max:120',
        ];
    }

    public function statusFilter(): ?string
    {
        return $this->validated('status');
    }

    public function search(): ?string
    {
        $search = trim((string) $this->validated('search'));

        return $search === '' ? null : $search;
    }
}
