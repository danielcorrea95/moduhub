<?php

namespace App\Modules\Signatures\Contracts;

use App\Modules\Signatures\Models\Document;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface DocumentRepositoryContract
{
    /**
     * @param  string|null  $statusFilter  chave de Document::LISTING_FILTERS
     */
    public function paginateForListing(?string $statusFilter = null, ?string $search = null, int $perPage = 20, ?string $externalId = null): LengthAwarePaginator;

    /**
     * @return Collection<string, int> total de documentos por status
     */
    public function countByStatus(): Collection;

    /**
     * Documentos aguardando assinatura, mais recentes primeiro, com signatários carregados.
     *
     * @return Collection<int, Document>
     */
    public function latestInProgress(int $limit = 5): Collection;

    public function findWithRelations(string $id): ?Document;

    public function findByShortId(string $shortId): ?Document;

    /** Documentos enviados para assinatura no intervalo, de um workspace (com ou sem tenant no contexto). */
    public function countSentBetween(string $tenantId, \DateTimeInterface $from, \DateTimeInterface $to): int;

    public function countCompletedBetween(string $tenantId, \DateTimeInterface $from, \DateTimeInterface $to): int;
}
