<?php

namespace App\Modules\Invoices\Services\Nfse;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;

/**
 * Leitura dos campos da NFS-e que o Moduhub guarda em coluna: os da nota emitida e o resumo
 * de uma nota de terceiros recebida pela distribuição do ADN.
 */
final readonly class NfseDocument
{
    public function __construct(
        public ?string $accessKey,
        public ?string $number,
        public ?CarbonImmutable $processedAt,
        public ?string $providerDocument = null,
        public ?string $providerName = null,
        public ?string $takerDocument = null,
        public ?string $takerName = null,
        public ?string $intermediaryDocument = null,
        public ?string $competence = null,
        public ?string $amount = null,
        public ?string $issAmount = null,
        public ?string $description = null,
    ) {}

    public static function fromXml(string $xml): self
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return new self(null, null, null);
        }

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('n', DpsBuilder::NS);
        $v = function (string $path) use ($xpath): ?string {
            $value = trim($xpath->evaluate("string(//n:infNFSe/{$path})"));

            return $value === '' ? null : $value;
        };
        $document = fn (string $base) => $v("{$base}/n:CNPJ") ?? $v("{$base}/n:CPF") ?? $v("{$base}/n:NIF");

        $id = (string) $xpath->evaluate('string(//n:infNFSe/@Id)');
        $processed = $v('n:dhProc');

        return new self(
            accessKey: preg_match('/(\d{50})$/', $id, $m) ? $m[1] : null,
            number: $v('n:nNFSe'),
            processedAt: $processed !== null ? CarbonImmutable::parse($processed) : null,
            providerDocument: $document('n:emit') ?? $document('n:DPS/n:infDPS/n:prest'),
            providerName: $v('n:emit/n:xNome') ?? $v('n:DPS/n:infDPS/n:prest/n:xNome'),
            takerDocument: $document('n:DPS/n:infDPS/n:toma'),
            takerName: $v('n:DPS/n:infDPS/n:toma/n:xNome'),
            intermediaryDocument: $document('n:DPS/n:infDPS/n:interm'),
            competence: $v('n:DPS/n:infDPS/n:dCompet'),
            amount: $v('n:DPS/n:infDPS/n:valores/n:vServPrest/n:vServ'),
            issAmount: $v('n:valores/n:vISSQN'),
            description: $v('n:DPS/n:infDPS/n:serv/n:cServ/n:xDescServ'),
        );
    }
}
