<?php

namespace App\Modules\Invoices\Services\Adn;

use App\Modules\Invoices\Contracts\MunicipalAgreementsContract;
use App\Modules\Invoices\Services\Nfse\IssuerCredentialsLoader;
use App\Modules\Invoices\Services\Sefin\IssuerHttp;
use Illuminate\Http\Client\PendingRequest;
use RuntimeException;
use Throwable;

class AdnAgreementsClient implements MunicipalAgreementsContract
{
    public function __construct(
        protected IssuerHttp $http,
        protected IssuerCredentialsLoader $credentials,
    ) {}

    public function check(string $environment, iterable $cityCodes, callable $onResult): void
    {
        $credentials = $this->credentials->platform();
        if ($credentials === null) {
            throw new RuntimeException('Configure o certificado da plataforma (SIGNATURES_PLATFORM_CERT_PATH e SIGNATURES_PLATFORM_CERT_PASSWORD) para consultar o ADN.');
        }

        $this->http->session(
            $credentials,
            (string) config("invoices.adn_urls.{$environment}"),
            (int) config('invoices.adn_timeout', 8),
            function (PendingRequest $http) use ($cityCodes, $onResult) {
                foreach ($cityCodes as $code) {
                    try {
                        $response = $http->get("/parametrizacao/{$code}/convenio");
                        $check = AgreementCheck::fromResponse($response->status(), $response->json());
                    } catch (Throwable $e) {
                        $check = AgreementCheck::unknown($e->getMessage());
                    }

                    $onResult((string) $code, $check);
                }
            },
        );
    }
}
