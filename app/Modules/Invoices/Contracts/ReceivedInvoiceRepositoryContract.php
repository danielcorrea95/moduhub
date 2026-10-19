<?php

namespace App\Modules\Invoices\Contracts;

use App\Modules\Invoices\Models\ReceivedInvoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ReceivedInvoiceRepositoryContract
{
    public function paginateForListing(?string $role, ?string $status, ?string $search, int $perPage = 20): LengthAwarePaginator;

    /** @return Collection<string, int> */
    public function countByRole(): Collection;

    /** Busca explícita pelo tenant: a sincronização roda em comando, sem sessão. */
    public function findForTenant(string $tenantId, string $environment, string $accessKey): ?ReceivedInvoice;
}
