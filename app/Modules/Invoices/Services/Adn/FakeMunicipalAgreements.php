<?php

namespace App\Modules\Invoices\Services\Adn;

use App\Modules\Invoices\Contracts\MunicipalAgreementsContract;

/** ADN em memória para os testes: municípios sem resposta programada saem como "não encontrado". */
class FakeMunicipalAgreements implements MunicipalAgreementsContract
{
    /** @var array<string, array<string, AgreementCheck>> por ambiente e código */
    protected array $answers = [];

    /** @var list<array{environment: string, code: string}> */
    public array $queries = [];

    public function answer(string $environment, string $code, AgreementCheck $check): static
    {
        $this->answers[$environment][$code] = $check;

        return $this;
    }

    public function check(string $environment, iterable $cityCodes, callable $onResult): void
    {
        foreach ($cityCodes as $code) {
            $this->queries[] = ['environment' => $environment, 'code' => (string) $code];
            $onResult((string) $code, $this->answers[$environment][$code] ?? new AgreementCheck(AgreementCheck::NOT_FOUND));
        }
    }
}
