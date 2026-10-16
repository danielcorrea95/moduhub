<?php

namespace App\Modules\Invoices\Services\Sefin;

use App\Modules\Invoices\Contracts\SefinClientContract;
use App\Modules\Invoices\Models\Issuer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

class SefinHttpClient implements SefinClientContract
{
    public function __construct(protected IssuerHttp $http) {}

    public function emit(Issuer $issuer, string $signedDpsXml): SefinResponse
    {
        return $this->toResponse($this->call($issuer, fn (PendingRequest $http) => $http->post('/nfse', [
            'dpsXmlGZipB64' => $this->pack($signedDpsXml),
        ])), 'nfseXmlGZipB64');
    }

    public function findAccessKeyByDps(Issuer $issuer, string $dpsId): ?string
    {
        $response = $this->call($issuer, fn (PendingRequest $http) => $http->get('/dps/'.$dpsId));

        if ($response->status() === 404) {
            return null;
        }

        $key = $this->field((array) $response->json(), 'chaveAcesso');

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function fetch(Issuer $issuer, string $accessKey): SefinResponse
    {
        return $this->toResponse($this->call($issuer, fn (PendingRequest $http) => $http->get('/nfse/'.$accessKey)), 'nfseXmlGZipB64');
    }

    public function registerEvent(Issuer $issuer, string $accessKey, string $signedEventXml): SefinResponse
    {
        return $this->toResponse($this->call($issuer, fn (PendingRequest $http) => $http->post('/nfse/'.$accessKey.'/eventos', [
            'pedidoRegistroEventoXmlGZipB64' => $this->pack($signedEventXml),
        ])), 'eventoXmlGZipB64');
    }

    /** @param  callable(PendingRequest): Response  $send */
    protected function call(Issuer $issuer, callable $send): Response
    {
        try {
            $response = $this->http->send($issuer, $this->baseUrl($issuer), (int) config('invoices.timeout', 30), $send);
        } catch (ConnectionException $e) {
            throw new SefinUnavailableException('Sem resposta da Sefin Nacional: '.$e->getMessage(), previous: $e);
        }

        if ($response->serverError() || $response->status() === 0) {
            Log::warning('Sefin Nacional respondeu com erro de servidor', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 2000)]);

            throw new SefinUnavailableException("A Sefin Nacional respondeu HTTP {$response->status()}. Tente sincronizar em alguns minutos.");
        }

        return $response;
    }

    protected function toResponse(Response $response, string $xmlField): SefinResponse
    {
        $body = (array) ($response->json() ?? []);
        $packed = $this->field($body, $xmlField);
        $accessKey = $this->field($body, 'chaveAcesso');
        $xml = is_string($packed) ? $this->unpack($packed) : null;

        $errors = $this->messages($this->field($body, 'erros') ?? $this->field($body, 'erro'));

        if (! $response->successful() && $errors === []) {
            // 401/403 de mTLS e respostas fora do contrato vêm sem a lista de erros.
            $errors = [[
                'code' => (string) $response->status(),
                'message' => $this->field($body, 'mensagem') ?? $this->field($body, 'title') ?? $this->field($body, 'detail')
                    ?? (trim(mb_substr(strip_tags($response->body()), 0, 300)) ?: 'Resposta sem descrição da Sefin Nacional.'),
                'complement' => null,
            ]];
        }

        return new SefinResponse(
            status: $response->status(),
            successful: $response->successful() && $errors === [],
            accessKey: is_string($accessKey) && $accessKey !== '' ? $accessKey : null,
            xml: $xml,
            errors: $errors,
            alerts: $this->messages($this->field($body, 'alertas')),
        );
    }

    /** @return list<array{code: string|null, message: string, complement: string|null}> */
    protected function messages(mixed $list): array
    {
        if (! is_array($list)) {
            return [];
        }

        // A Sefin às vezes devolve um objeto único no lugar da lista.
        if (! array_is_list($list)) {
            $list = [$list];
        }

        return array_values(array_map(function ($item) {
            if (! is_array($item)) {
                return ['code' => null, 'message' => (string) $item, 'complement' => null];
            }

            $code = (string) ($this->field($item, 'codigo') ?? '');
            $complement = (string) ($this->field($item, 'complemento') ?? '');

            return [
                'code' => $code !== '' ? $code : null,
                'message' => (string) ($this->field($item, 'descricao') ?? $this->field($item, 'mensagem') ?? ''),
                'complement' => $complement !== '' ? $complement : null,
            ];
        }, $list));
    }

    /** Os exemplos da Sefin variam a caixa das chaves (Codigo, codigo, idDPS, idDps). */
    protected function field(array $data, string $name): mixed
    {
        foreach ($data as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    protected function baseUrl(Issuer $issuer): string
    {
        return rtrim((string) config('invoices.sefin_urls.'.($issuer->isProduction() ? 'production' : 'restricted')), '/');
    }

    protected function pack(string $xml): string
    {
        return base64_encode(gzencode($xml));
    }

    protected function unpack(string $packed): ?string
    {
        $binary = base64_decode($packed, true);
        if ($binary === false) {
            return null;
        }

        $xml = @gzdecode($binary);

        return $xml === false ? null : $xml;
    }
}
