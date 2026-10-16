<?php

namespace App\Modules\Invoices\Services\Nfse;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;

/**
 * Leitura dos poucos campos da NFS-e devolvida pela Sefin que o Moduhub guarda em coluna.
 */
final readonly class NfseDocument
{
    public function __construct(
        public ?string $accessKey,
        public ?string $number,
        public ?CarbonImmutable $processedAt,
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

        $id = $xpath->evaluate('string(//n:infNFSe/@Id)');
        $number = $xpath->evaluate('string(//n:infNFSe/n:nNFSe)');
        $processed = $xpath->evaluate('string(//n:infNFSe/n:dhProc)');

        return new self(
            accessKey: preg_match('/(\d{50})$/', $id, $m) ? $m[1] : null,
            number: $number !== '' ? $number : null,
            processedAt: $processed !== '' ? CarbonImmutable::parse($processed) : null,
        );
    }
}
