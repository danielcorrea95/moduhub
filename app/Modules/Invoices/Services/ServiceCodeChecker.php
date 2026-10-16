<?php

namespace App\Modules\Invoices\Services;

use App\Modules\Invoices\Contracts\MunicipalParametersContract;
use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Services\Adn\ServiceCodeCheck;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Checagem prévia do código de serviço nos parâmetros do município, para avisar antes da rejeição E0312.
 */
class ServiceCodeChecker
{
    public function __construct(protected MunicipalParametersContract $parameters) {}

    public function check(Issuer $issuer, string $nationalCode, ?string $municipalCode, ?string $serviceCityCode, CarbonInterface $competence): ServiceCodeCheck
    {
        // Pela LC 116 o ISS é, em regra, do município do prestador, mas alguns serviços são tributados onde
        // são prestados. Com locais diferentes não dá para saber qual município consultar: melhor não checar
        // do que bloquear uma nota válida.
        if ($serviceCityCode !== null && $serviceCityCode !== '' && $serviceCityCode !== $issuer->city_code) {
            return ServiceCodeCheck::unknown(ServiceCodeCheck::formatCode($nationalCode, $municipalCode));
        }

        return $this->parameters->serviceRate($issuer, $issuer->city_code, $nationalCode, $municipalCode, $competence);
    }

    /**
     * @param  array{national_tax_code: string, municipal_tax_code?: string|null, city_code?: string|null}  $service
     *
     * @throws ValidationException quando o ADN responde que o município não parametrizou o código.
     */
    public function assertIssuable(Issuer $issuer, array $service, CarbonInterface $competence): void
    {
        $check = $this->check($issuer, $service['national_tax_code'], $service['municipal_tax_code'] ?? null, $service['city_code'] ?? null, $competence);

        if ($check->isNotParametrized()) {
            throw ValidationException::withMessages([
                'service.national_tax_code' => $this->notParametrizedMessage($issuer, $check, $competence),
            ]);
        }
    }

    public function notParametrizedMessage(Issuer $issuer, ServiceCodeCheck $check, CarbonInterface $competence): string
    {
        return sprintf(
            'O código %s não está parametrizado em %s para a competência %s, e a Sefin rejeitaria a nota (E0312). '
            .'Confira com o contador o código de tributação nacional e o desdobramento municipal.',
            $check->serviceCode,
            $issuer->city_name ?: 'no município do emitente',
            $competence->format('d/m/Y'),
        );
    }
}
