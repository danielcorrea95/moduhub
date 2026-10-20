<?php

namespace App\Modules\Mailing\Contracts;

use App\Modules\Mailing\Models\EmailSuppression;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface EmailSuppressionRepositoryContract
{
    /**
     * Grava ou atualiza o bloqueio do endereço no workspace, fora do scope: roda no webhook do SES.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function put(string $tenantId, string $email, array $attributes): EmailSuppression;

    public function find(string $tenantId, string $email): ?EmailSuppression;

    public function paginateForListing(?string $search, int $perPage = 25): LengthAwarePaginator;

    public function count(): int;

    public function delete(EmailSuppression $suppression): void;
}
