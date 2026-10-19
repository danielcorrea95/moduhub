<?php

namespace App\Modules\Invoices\Contracts;

use App\Modules\Invoices\Models\MunicipalAgreement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface MunicipalAgreementRepositoryContract
{
    /**
     * @param  array{uf?: string|null, search?: string|null, situation?: string|null}  $filters
     */
    public function paginate(string $environment, array $filters, int $perPage = 50): LengthAwarePaginator;

    /** @return Collection<string, int> total por situação (national_emitter, adn_only, inactive, not_found) */
    public function countBySituation(string $environment, ?string $uf = null): Collection;

    public function find(string $cityCode, string $environment): ?MunicipalAgreement;

    public function lastCheckedAt(string $environment): ?Carbon;

    /** @param  list<array<string, mixed>>  $rows */
    public function upsert(array $rows): void;
}
