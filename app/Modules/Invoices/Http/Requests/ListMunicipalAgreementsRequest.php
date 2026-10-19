<?php

namespace App\Modules\Invoices\Http\Requests;

use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Repositories\MunicipalAgreementRepository;
use App\Modules\Invoices\Services\Nfse\Municipalities;
use Illuminate\Foundation\Http\FormRequest;

class ListMunicipalAgreementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Invoice::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'uf' => $this->filled('uf') ? strtoupper(trim((string) $this->input('uf'))) : null,
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'environment' => 'nullable|in:'.implode(',', Issuer::ENVIRONMENTS),
            'uf' => 'nullable|in:'.implode(',', Municipalities::states()),
            'search' => 'nullable|string|max:80',
            'situation' => 'nullable|in:'.implode(',', MunicipalAgreementRepository::SITUATIONS),
            'per_page' => 'nullable|integer|min:1|max:200',
        ];
    }

    public function environment(): string
    {
        return $this->validated('environment') ?? Issuer::ENV_PRODUCTION;
    }

    /** @return array{uf: string|null, search: string|null, situation: string|null} */
    public function filters(): array
    {
        return [
            'uf' => $this->validated('uf'),
            'search' => $this->validated('search'),
            'situation' => $this->validated('situation'),
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 50);
    }
}
