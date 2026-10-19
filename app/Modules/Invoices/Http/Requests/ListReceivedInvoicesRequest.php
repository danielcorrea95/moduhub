<?php

namespace App\Modules\Invoices\Http\Requests;

use App\Modules\Invoices\Models\ReceivedInvoice;
use Illuminate\Foundation\Http\FormRequest;

class ListReceivedInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ReceivedInvoice::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'role' => 'nullable|in:'.implode(',', array_keys(ReceivedInvoice::ROLE_LABELS)),
            'status' => 'nullable|in:'.implode(',', ReceivedInvoice::STATUSES),
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function role(): ?string
    {
        return $this->validated('role');
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
