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

    /**
     * Nota já criada para o mesmo pedido do sistema do cliente e que ainda vale (não rejeitada).
     * O tenant é explícito porque o external_id só é único dentro de um workspace.
     */
    public function findReusableByExternalId(string $tenantId, string $externalId): ?Invoice;

    /** Substituta da nota ainda sem resposta da Sefin. */
    public function findPendingReplacement(Invoice $original): ?Invoice;

    /** Nota emitida do workspace atual, base para uma substituição. */
    public function findIssued(string $id): ?Invoice;

    /** Notas que contam na cota: toda DPS do período que não foi rejeitada (inclui pendentes, canceladas e substituídas). */
    public function countBilledBetween(string $tenantId, \DateTimeInterface $start, \DateTimeInterface $end): int;

    /** Nota nossa pela chave de acesso. Busca explícita pelo tenant: a sincronização roda em comando, sem sessão. */
    public function findByAccessKeyForTenant(string $tenantId, string $accessKey): ?Invoice;

    /** Nota rejeitada do workspace atual, usada como rascunho para corrigir e reenviar. */
    public function findRejected(string $id): ?Invoice;
}
