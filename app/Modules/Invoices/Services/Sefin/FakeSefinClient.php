<?php

namespace App\Modules\Invoices\Services\Sefin;

use App\Modules\Invoices\Contracts\SefinClientContract;
use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Services\Nfse\DpsBuilder;
use DOMDocument;
use DOMXPath;

/**
 * Sefin em memória para testes e desenvolvimento sem e-CNPJ. Gera NFS-e com chave de 50 dígitos
 * e permite programar a próxima resposta (rejeição ou indisponibilidade).
 */
class FakeSefinClient implements SefinClientContract
{
    /** @var array<string, array{access_key: string, xml: string}> notas por Id da DPS */
    protected array $issued = [];

    /** @var array<string, string> eventos por chave de acesso */
    protected array $events = [];

    /** @var list<array{method: string, path: string, xml: string|null}> */
    public array $requests = [];

    protected ?array $nextErrors = null;

    protected bool $nextUnavailable = false;

    /** Emite a nota mas falha antes de responder, como num timeout depois do processamento. */
    protected bool $nextLostResponse = false;

    protected int $sequence = 0;

    /** @param  list<array{code: string|null, message: string, complement?: string|null}>  $errors */
    public function rejectNext(array $errors): static
    {
        $this->nextErrors = array_map(fn (array $e) => $e + ['complement' => null], $errors);

        return $this;
    }

    public function failNext(): static
    {
        $this->nextUnavailable = true;

        return $this;
    }

    public function loseNextResponse(): static
    {
        $this->nextLostResponse = true;

        return $this;
    }

    public function emit(Issuer $issuer, string $signedDpsXml): SefinResponse
    {
        $this->requests[] = ['method' => 'POST', 'path' => '/nfse', 'xml' => $signedDpsXml];
        $this->throwIfUnavailable();

        if ($rejection = $this->takeRejection()) {
            return $rejection;
        }

        $dpsId = $this->dpsId($signedDpsXml);
        $this->issued[$dpsId] ??= $this->makeNfse($issuer, $signedDpsXml);

        if ($this->nextLostResponse) {
            $this->nextLostResponse = false;

            throw new SefinUnavailableException('Sem resposta da Sefin Nacional (simulado).');
        }

        return new SefinResponse(201, true, $this->issued[$dpsId]['access_key'], $this->issued[$dpsId]['xml']);
    }

    public function findAccessKeyByDps(Issuer $issuer, string $dpsId): ?string
    {
        $this->requests[] = ['method' => 'GET', 'path' => '/dps/'.$dpsId, 'xml' => null];
        $this->throwIfUnavailable();

        return $this->issued[$dpsId]['access_key'] ?? null;
    }

    public function fetch(Issuer $issuer, string $accessKey): SefinResponse
    {
        $this->requests[] = ['method' => 'GET', 'path' => '/nfse/'.$accessKey, 'xml' => null];
        $this->throwIfUnavailable();

        foreach ($this->issued as $nfse) {
            if ($nfse['access_key'] === $accessKey) {
                return new SefinResponse(200, true, $accessKey, $nfse['xml']);
            }
        }

        return new SefinResponse(404, false, errors: [['code' => 'E404', 'message' => 'NFS-e não encontrada.', 'complement' => null]]);
    }

    public function registerEvent(Issuer $issuer, string $accessKey, string $signedEventXml): SefinResponse
    {
        $this->requests[] = ['method' => 'POST', 'path' => '/nfse/'.$accessKey.'/eventos', 'xml' => $signedEventXml];
        $this->throwIfUnavailable();

        if ($rejection = $this->takeRejection()) {
            return $rejection;
        }

        if (isset($this->events[$accessKey])) {
            return new SefinResponse(400, false, errors: [['code' => 'E0840', 'message' => 'Evento de cancelamento já registrado para esta NFS-e.', 'complement' => null]]);
        }

        $this->events[$accessKey] = '<evento xmlns="'.DpsBuilder::NS.'" versao="1.01"><infEvento Id="EVT'.$accessKey.'101101001">'
            .'<dhProc>'.now()->format('Y-m-d\TH:i:sP').'</dhProc>'.preg_replace('/^<\?xml[^>]*>/', '', $signedEventXml)
            .'</infEvento></evento>';

        return new SefinResponse(201, true, $accessKey, $this->events[$accessKey]);
    }

    protected function throwIfUnavailable(): void
    {
        if ($this->nextUnavailable) {
            $this->nextUnavailable = false;

            throw new SefinUnavailableException('Sem resposta da Sefin Nacional (simulado).');
        }
    }

    protected function takeRejection(): ?SefinResponse
    {
        if ($this->nextErrors === null) {
            return null;
        }

        $errors = $this->nextErrors;
        $this->nextErrors = null;

        return new SefinResponse(400, false, errors: $errors);
    }

    /** @return array{access_key: string, xml: string} */
    protected function makeNfse(Issuer $issuer, string $dpsXml): array
    {
        $number = ++$this->sequence;
        $accessKey = $issuer->city_code
            .($issuer->isProduction() ? '1' : '2')
            .'2'
            .str_pad($issuer->cnpj, 14, '0', STR_PAD_LEFT)
            .str_pad((string) $number, 13, '0', STR_PAD_LEFT)
            .now()->format('ym')
            .str_pad((string) random_int(0, 999_999_999), 9, '0', STR_PAD_LEFT);
        $accessKey .= (string) (array_sum(str_split($accessKey)) % 10);

        $xml = '<?xml version="1.0" encoding="UTF-8"?><NFSe xmlns="'.DpsBuilder::NS.'" versao="1.01">'
            .'<infNFSe Id="NFS'.$accessKey.'"><xLocEmi>Simulado</xLocEmi><nNFSe>'.$number.'</nNFSe>'
            .'<dhProc>'.now()->format('Y-m-d\TH:i:sP').'</dhProc>'
            .preg_replace('/^<\?xml[^>]*>/', '', $dpsXml)
            .'</infNFSe></NFSe>';

        return ['access_key' => $accessKey, 'xml' => $xml];
    }

    protected function dpsId(string $xml): string
    {
        $doc = new DOMDocument;
        $doc->loadXML($xml);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('n', DpsBuilder::NS);

        return $xpath->evaluate('string(//n:infDPS/@Id)');
    }
}
