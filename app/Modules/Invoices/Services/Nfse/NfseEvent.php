<?php

namespace App\Modules\Invoices\Services\Nfse;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Evento registrado numa NFS-e (XML evento v1.01): o código vem do nome do grupo dentro do
 * pedido (e101101, e203206...), que é a fonte mais segura, não do rótulo do lote.
 */
final readonly class NfseEvent
{
    public const LABELS = [
        '101101' => 'Cancelamento',
        '105102' => 'Cancelamento por substituição',
        '101103' => 'Pedido de análise fiscal para cancelamento',
        '105104' => 'Cancelamento deferido pela análise fiscal',
        '105105' => 'Cancelamento indeferido pela análise fiscal',
        '202201' => 'Confirmação do prestador',
        '203202' => 'Confirmação do tomador',
        '204203' => 'Confirmação do intermediário',
        '205204' => 'Confirmação tácita',
        '202205' => 'Rejeição do prestador',
        '203206' => 'Rejeição do tomador',
        '204207' => 'Rejeição do intermediário',
        '205208' => 'Anulação da rejeição',
        '305101' => 'Cancelamento por ofício',
        '305102' => 'Bloqueio por ofício',
        '305103' => 'Desbloqueio por ofício',
    ];

    public function __construct(
        public ?string $code,
        public ?string $accessKey,
        public ?string $reasonCode,
        public ?string $reason,
        public ?string $authorDocument,
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
            return new self(null, null, null, null, null, null);
        }

        $xp = new DOMXPath($doc);
        $xp->registerNamespace('n', DpsBuilder::NS);
        $v = function (string $path) use ($xp): ?string {
            $value = trim((string) $xp->evaluate("string({$path})"));

            return $value === '' ? null : $value;
        };

        $code = null;
        $group = null;
        foreach ($xp->query('//n:infPedReg/*') as $node) {
            if ($node instanceof DOMElement && preg_match('/^e(\d{6})$/', $node->localName, $m)) {
                [$code, $group] = [$m[1], $node];
                break;
            }
        }

        $processed = $v('//n:infEvento/n:dhProc') ?? $v('//n:infPedReg/n:dhEvento');

        return new self(
            code: $code,
            accessKey: $v('//n:infPedReg/n:chNFSe'),
            reasonCode: $group ? self::child($xp, $group, 'cMotivo') : null,
            reason: $group ? self::child($xp, $group, 'xMotivo') : null,
            authorDocument: $v('//n:infPedReg/n:CNPJAutor') ?? $v('//n:infPedReg/n:CPFAutor'),
            processedAt: $processed !== null ? CarbonImmutable::parse($processed) : null,
        );
    }

    public function label(): string
    {
        return self::LABELS[$this->code ?? ''] ?? 'Evento '.($this->code ?? 'desconhecido');
    }

    private static function child(DOMXPath $xp, DOMElement $group, string $name): ?string
    {
        $value = trim((string) $xp->evaluate("string(n:{$name})", $group));

        return $value === '' ? null : $value;
    }
}
