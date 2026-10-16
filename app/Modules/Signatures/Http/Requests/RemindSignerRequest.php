<?php

namespace App\Modules\Signatures\Http\Requests;

use App\Modules\Signatures\Models\Document;
use Illuminate\Foundation\Http\FormRequest;

class RemindSignerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof Document
            && $this->user()?->can('remindSigners', $document);
    }

    public function rules(): array
    {
        return [];
    }
}
