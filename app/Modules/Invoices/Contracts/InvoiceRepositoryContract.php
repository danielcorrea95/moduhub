<?php

namespace App\Modules\Invoices\Contracts;

use App\Modules\Invoices\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface InvoiceRepositoryContract
{
    public function paginateForListing(?string $status = null, ?string $search = null, int $perPage = 20, ?string $externalId = null): LengthAwarePaginator;

    /** @return Collection<string, int> total de notas por status */
    public function countByStatus(): Collection;

    /** Nota já criada para o mesmo pedido do sistema do cliente e que ainda vale (não rejeitada). */
    public function findReusableByExternalId(string $externalId): ?Invoice;

    /** Nota rejeitada do workspace atual, usada como rascunho para corrigir e reenviar. */
    public function findRejected(string $id): ?Invoice;
}
