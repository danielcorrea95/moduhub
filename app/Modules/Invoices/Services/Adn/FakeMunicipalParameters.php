<?php

namespace App\Modules\Invoices\Services\Adn;

use App\Modules\Invoices\Contracts\MunicipalParametersContract;
use App\Modules\Invoices\Models\Issuer;
use Carbon\CarbonInterface;

/**
 * ADN em memória. Sem programação, responde `unknown` para não interferir nos demais testes.
 */
class FakeMunicipalParameters implements MunicipalParametersContract
{
    /** @var array<string, float|null> alíquota por "município|código" parametrizado */
    protected array $rates = [];

    /** @var array<string, true> "município|código" sem parametrização */
    protected array $missing = [];

    /** @var list<string> */
    public array $queries = [];

    public function parametrize(string $cityCode, string $serviceCode, ?float $rate): static
    {
        $this->rates[$cityCode.'|'.$serviceCode] = $rate;

        return $this;
    }

    public function withoutParametrization(string $cityCode, string $serviceCode): static
    {
        $this->missing[$cityCode.'|'.$serviceCode] = true;

        return $this;
    }

    public function serviceRate(Issuer $issuer, string $cityCode, string $nationalCode, ?string $municipalCode, CarbonInterface $competence): ServiceCodeCheck
    {
        $code = ServiceCodeCheck::formatCode($nationalCode, $municipalCode);
        $key = $cityCode.'|'.$code;
        $this->queries[] = $key;

        return match (true) {
            isset($this->missing[$key]) => ServiceCodeCheck::notParametrized($code),
            array_key_exists($key, $this->rates) => ServiceCodeCheck::parametrized($code, $this->rates[$key]),
            default => ServiceCodeCheck::unknown($code),
        };
    }
}
