<?php

namespace App\Modules\Mailing\Http\Requests;

use App\Modules\Mailing\Enums\MailboxProvider;
use App\Modules\Mailing\Services\Report\ReportPeriod;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros das telas de e-mails enviados. Destinatário e assunto são dado de terceiros: só dono e
 * administrador do workspace veem.
 */
class EmailLogRequest extends FormRequest
{
    public const STATUSES = ['delivered', 'opened', 'clicked', 'failed', 'complained', 'pending'];

    public function authorize(): bool
    {
        $tenant = app(CurrentTenant::class)->get();

        return $tenant !== null && $this->user()?->can('update', $tenant);
    }

    public function rules(): array
    {
        return [
            'periodo' => ['nullable', 'integer', Rule::in(ReportPeriod::OPTIONS)],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'provedor' => ['nullable', Rule::enum(MailboxProvider::class)],
            'busca' => ['nullable', 'string', 'max:120'],
            'mensagem' => ['nullable', 'ulid'],
        ];
    }

    public function period(): ReportPeriod
    {
        return ReportPeriod::lastDays($this->filled('periodo') ? (int) $this->validated('periodo') : null);
    }

    public function search(): ?string
    {
        $search = trim((string) $this->validated('busca'));

        return $search === '' ? null : $search;
    }

    /** @return array{status: ?string, provider: ?string, search: ?string} */
    public function filters(): array
    {
        return [
            'status' => $this->validated('status'),
            'provider' => $this->validated('provedor'),
            'search' => $this->search(),
        ];
    }
}
