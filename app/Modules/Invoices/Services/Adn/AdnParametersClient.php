<?php

namespace App\Modules\Invoices\Services\Adn;

use App\Modules\Invoices\Contracts\MunicipalParametersContract;
use App\Modules\Invoices\Models\CertificateEvent;
use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Services\Sefin\IssuerHttp;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * GET {adn}/parametrizacao/{município}/{código}/{competência}/aliquota. Validado contra o ADN real
 * em 16/10/2026: 200 com a alíquota vigente, 404 "Alíquotas não encontradas" quando o município não
 * parametrizou o código naquela data.
 */
class AdnParametersClient implements MunicipalParametersContract
{
    public function __construct(protected IssuerHttp $http) {}

    public function serviceRate(Issuer $issuer, string $cityCode, string $nationalCode, ?string $municipalCode, CarbonInterface $competence): ServiceCodeCheck
    {
        $code = ServiceCodeCheck::formatCode($nationalCode, $municipalCode);
        $baseUrl = (string) config('invoices.adn_urls.'.($issuer->isProduction() ? 'production' : 'restricted'));

        try {
            $response = $this->http->send(
                $issuer,
                CertificateEvent::PURPOSE_ADN_AUTHENTICATION,
                'consulta da alíquota do serviço',
                $baseUrl,
                (int) config('invoices.adn_timeout', 8),
                fn (PendingRequest $http) => $http->get("/parametrizacao/{$cityCode}/{$code}/{$competence->format('Y-m-d')}/aliquota"),
            );
        } catch (ValidationException $e) {
            // Certificado ausente ou ilegível: quem avisa é a emissão, não a checagem prévia.
            return ServiceCodeCheck::unknown($code);
        } catch (Throwable $e) {
            Log::info('ADN indisponível na checagem do código de serviço', ['code' => $code, 'error' => $e->getMessage()]);

            return ServiceCodeCheck::unknown($code);
        }

        if ($response->status() === 404) {
            return ServiceCodeCheck::notParametrized($code);
        }

        if (! $response->successful()) {
            return ServiceCodeCheck::unknown($code);
        }

        // A resposta indexa as alíquotas pelo próprio código, que tem pontos: lido direto do array.
        $all = (array) $response->json('aliquotas');
        $rates = $all[$code] ?? (reset($all) ?: null);
        $rate = is_array($rates) && isset($rates[0]['Aliq']) ? (float) $rates[0]['Aliq'] : null;

        return ServiceCodeCheck::parametrized($code, $rate);
    }
}
