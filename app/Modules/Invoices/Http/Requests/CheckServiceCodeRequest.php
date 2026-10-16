<?php

namespace App\Modules\Invoices\Http\Requests;

use App\Modules\Invoices\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class CheckServiceCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('issue', Invoice::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $digits = fn (string $key) => $this->filled($key) ? preg_replace('/\D/', '', (string) $this->input($key)) : null;

        $this->merge([
            'national_tax_code' => $digits('national_tax_code'),
            'municipal_tax_code' => $digits('municipal_tax_code'),
            'city_code' => $digits('city_code'),
        ]);
    }

    public function rules(): array
    {
        return [
            'national_tax_code' => 'required|digits:6',
            'municipal_tax_code' => 'nullable|digits:3',
            'city_code' => 'nullable|digits:7',
            'competence_date' => 'nullable|date_format:Y-m-d',
        ];
    }

    public function competence(): Carbon
    {
        return Carbon::parse($this->validated('competence_date') ?? now()->toDateString());
    }
}
