<?php

namespace App\Modules\Invoices\Http\Requests;

use App\Modules\Invoices\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class ListInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Invoice::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|in:'.implode(',', Invoice::STATUSES),
            'search' => 'nullable|string|max:120',
            'external_id' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function status(): ?string
    {
        return $this->validated('status');
    }

    public function search(): ?string
    {
        $search = trim((string) $this->validated('search'));

        return $search === '' ? null : $search;
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 20);
    }
}
