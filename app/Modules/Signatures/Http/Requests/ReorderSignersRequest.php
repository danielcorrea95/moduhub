<?php

namespace App\Modules\Signatures\Http\Requests;

use App\Modules\Signatures\Models\Document;
use Illuminate\Foundation\Http\FormRequest;

class ReorderSignersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');
        return $document instanceof Document
            && $this->user()?->can('manageSigners', $document);
    }

    public function rules(): array
    {
        return [
            'order' => 'required|array|min:1',
            'order.*' => 'required|string|size:26',
        ];
    }
}
