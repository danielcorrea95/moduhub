<?php

namespace App\Modules\Invoices\Services\Adn;

use App\Modules\Invoices\Contracts\DfeDistributionContract;
use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Services\Sefin\IssuerHttp;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Throwable;

/**
 * Validado contra o ADN real em 19/10/2026: 200 com StatusProcessamento DOCUMENTOS_LOCALIZADOS e
 * LoteDFe (ArquivoXml em gzip + base64); 404 NENHUM_DOCUMENTO_LOCALIZADO (E2220) no fim da fila.
 */
class AdnDistributionClient implements DfeDistributionContract
{
    public function __construct(protected IssuerHttp $http) {}

    public function fetch(Issuer $issuer, int $afterNsu): DfeBatch
    {
        $baseUrl = rtrim((string) config('invoices.adn_urls.'.($issuer->isProduction() ? 'production' : 'restricted')), '/').'/contribuintes';

        try {
            $response = $this->http->send(
                $issuer,
                $baseUrl,
                (int) config('invoices.timeout', 30),
                fn (PendingRequest $http) => $http->get("/DFe/{$afterNsu}", ['lote' => 'true']),
            );
        } catch (Throwable $e) {
            return DfeBatch::failed('ADN indisponível: '.$e->getMessage());
        }

        $body = (array) $response->json();
        $status = $body['StatusProcessamento'] ?? null;

        if ($status === 'NENHUM_DOCUMENTO_LOCALIZADO') {
            return new DfeBatch([], true);
        }

        if (! $response->successful() || $status !== 'DOCUMENTOS_LOCALIZADOS') {
            $error = $body['Erros'][0] ?? null;

            return DfeBatch::failed(is_array($error)
                ? trim(($error['Codigo'] ?? '').' '.($error['Descricao'] ?? ''))
                : "Resposta inesperada do ADN (HTTP {$response->status()}).");
        }

        $documents = [];
        foreach ((array) ($body['LoteDFe'] ?? []) as $item) {
            $xml = isset($item['ArquivoXml']) ? @gzdecode((string) base64_decode((string) $item['ArquivoXml'], true)) : false;
            if ($xml === false || ! isset($item['NSU'], $item['ChaveAcesso'])) {
                continue;
            }

            $documents[] = new DistributedDfe(
                nsu: (int) $item['NSU'],
                accessKey: (string) $item['ChaveAcesso'],
                type: (string) ($item['TipoDocumento'] ?? ''),
                eventType: isset($item['TipoEvento']) ? (string) $item['TipoEvento'] : null,
                xml: $xml,
                generatedAt: isset($item['DataHoraGeracao']) ? CarbonImmutable::parse($item['DataHoraGeracao'], 'America/Sao_Paulo') : null,
            );
        }

        return new DfeBatch($documents, false);
    }
}
