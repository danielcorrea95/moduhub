<?php

namespace App\Modules\Invoices\Http\Requests;

use App\Modules\Invoices\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class SearchMunicipalitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Invoice::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'q' => 'required|string|min:2|max:80',
        ];
    }

    public function term(): string
    {
        return (string) $this->validated('q');
    }
}
