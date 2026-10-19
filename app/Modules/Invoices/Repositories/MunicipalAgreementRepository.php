<?php

namespace App\Modules\Invoices\Repositories;

use App\Modules\Invoices\Contracts\MunicipalAgreementRepositoryContract;
use App\Modules\Invoices\Models\MunicipalAgreement;
use App\Modules\Invoices\Services\Adn\AgreementCheck;
use App\Modules\Invoices\Services\Nfse\Municipalities;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MunicipalAgreementRepository implements MunicipalAgreementRepositoryContract
{
    /** Situações da listagem, derivadas de status + national_emitter. */
    public const SITUATION_NATIONAL_EMITTER = 'national_emitter';

    public const SITUATION_ADN_ONLY = 'adn_only';

    public const SITUATION_INACTIVE = 'inactive';

    public const SITUATION_NOT_FOUND = 'not_found';

    public const SITUATIONS = [
        self::SITUATION_NATIONAL_EMITTER,
        self::SITUATION_ADN_ONLY,
        self::SITUATION_INACTIVE,
        self::SITUATION_NOT_FOUND,
    ];

    public function paginate(string $environment, array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $this->query($environment, $filters['uf'] ?? null)
            ->when(! empty($filters['search']), function (Builder $q) use ($filters) {
                // O nome não está no banco: vem da tabela do IBGE, então o filtro vira uma lista de códigos.
                $search = (string) $filters['search'];
                $codes = ctype_digit($search) ? [$search] : Municipalities::codesMatching($search);
                $q->whereIn('city_code', $codes);
            })
            ->when(! empty($filters['situation']), fn (Builder $q) => $this->situation($q, (string) $filters['situation']))
            ->orderBy('city_code')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function countBySituation(string $environment, ?string $uf = null): Collection
    {
        return collect(self::SITUATIONS)->mapWithKeys(fn (string $situation) => [
            $situation => $this->situation($this->query($environment, $uf), $situation)->count(),
        ]);
    }

    public function find(string $cityCode, string $environment): ?MunicipalAgreement
    {
        return MunicipalAgreement::query()->where('city_code', $cityCode)->where('environment', $environment)->first();
    }

    public function lastCheckedAt(string $environment): ?Carbon
    {
        $value = MunicipalAgreement::query()->where('environment', $environment)->max('checked_at');

        return $value === null ? null : Carbon::parse($value);
    }

    public function upsert(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $now = now();
        MunicipalAgreement::query()->upsert(
            array_map(fn (array $row) => $row + ['created_at' => $now, 'updated_at' => $now], $rows),
            ['city_code', 'environment'],
            ['status', 'national_environment', 'national_emitter', 'rfb_standard_issuance', 'national_assessment', 'credit_use', 'message', 'checked_at', 'updated_at'],
        );
    }

    protected function query(string $environment, ?string $uf): Builder
    {
        return MunicipalAgreement::query()
            ->where('environment', $environment)
            // O código IBGE começa pelo código da UF: filtra sem precisar guardar a sigla.
            ->when($uf !== null && $uf !== '', fn (Builder $q) => $q->whereIn('city_code', Municipalities::codes([$uf])));
    }

    protected function situation(Builder $q, string $situation): Builder
    {
        return match ($situation) {
            self::SITUATION_NATIONAL_EMITTER => $q->where('status', AgreementCheck::ACTIVE)->where('national_emitter', true),
            self::SITUATION_ADN_ONLY => $q->where('status', AgreementCheck::ACTIVE)->where(fn (Builder $w) => $w->where('national_emitter', false)->orWhereNull('national_emitter')),
            self::SITUATION_INACTIVE => $q->where('status', AgreementCheck::INACTIVE),
            default => $q->where('status', AgreementCheck::NOT_FOUND),
        };
    }
}
