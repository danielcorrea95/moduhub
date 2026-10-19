<?php

namespace App\Modules\Invoices\Services\Nfse;

use App\Modules\Invoices\Models\Issuer;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;

/**
 * Pedido de registro de evento (pedRegEvento v1.01) para qualquer evento que o contribuinte registra:
 * cancelamento, análise fiscal e manifestações. Id = PRE + chave + código do evento.
 */
class EventRequestBuilder
{
    public const CANCELLATION = '101101';

    public const CANCELLATION_REVIEW = '101103';

    public const PROVIDER_CONFIRMATION = '202201';

    public const TAKER_CONFIRMATION = '203202';

    public const INTERMEDIARY_CONFIRMATION = '204203';

    public const PROVIDER_REJECTION = '202205';

    public const TAKER_REJECTION = '203206';

    public const INTERMEDIARY_REJECTION = '204207';

    /** xDesc fixo de cada evento (enumeração do XSD) e se ele leva motivo. */
    private const EVENTS = [
        self::CANCELLATION => ['Cancelamento de NFS-e', 'required'],
        self::CANCELLATION_REVIEW => ['Solicitação de Análise Fiscal para Cancelamento de NFS-e', 'required'],
        self::PROVIDER_CONFIRMATION => ['Manifestação de NFS-e - Confirmação do Prestador', 'none'],
        self::TAKER_CONFIRMATION => ['Manifestação de NFS-e - Confirmação do Tomador', 'none'],
        self::INTERMEDIARY_CONFIRMATION => ['Manifestação de NFS-e - Confirmação do Intermediário', 'none'],
        self::PROVIDER_REJECTION => ['Manifestação de NFS-e - Rejeição do Prestador', 'optional'],
        self::TAKER_REJECTION => ['Manifestação de NFS-e - Rejeição do Tomador', 'optional'],
        self::INTERMEDIARY_REJECTION => ['Manifestação de NFS-e - Rejeição do Intermediário', 'optional'],
    ];

    /** @param  string|int|null  $reasonCode  cMotivo; null nos eventos de confirmação */
    public function build(string $accessKey, Issuer $author, string $event, string|int|null $reasonCode = null, ?string $reason = null): DOMDocument
    {
        if (! isset(self::EVENTS[$event])) {
            throw new InvalidArgumentException("Evento {$event} não é registrado pelo contribuinte.");
        }
        [$description, $reasonMode] = self::EVENTS[$event];

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $root = $doc->createElementNS(DpsBuilder::NS, 'pedRegEvento');
        $root->setAttribute('versao', (string) config('invoices.layout_version'));
        $doc->appendChild($root);

        $inf = $this->el($root, 'infPedReg');
        $inf->setAttribute('Id', 'PRE'.$accessKey.$event);

        $this->el($inf, 'tpAmb', $author->isProduction() ? '1' : '2');
        $this->el($inf, 'verAplic', (string) config('invoices.app_version'));
        // Um minuto de folga, como na DPS: a Sefin rejeita evento com hora à frente do relógio dela.
        $this->el($inf, 'dhEvento', now()->subMinute()->setTimezone('America/Sao_Paulo')->format('Y-m-d\TH:i:sP'));
        $this->el($inf, 'CNPJAutor', $author->cnpj);
        $this->el($inf, 'chNFSe', $accessKey);

        $node = $this->el($inf, 'e'.$event);
        $this->el($node, 'xDesc', $description);
        if ($reasonMode !== 'none') {
            $this->el($node, 'cMotivo', (string) $reasonCode);
            $text = NfseText::clean($reason);
            if ($reasonMode === 'required' || $text !== null) {
                $this->el($node, 'xMotivo', $text ?? '');
            }
        }

        return $doc;
    }

    private function el(DOMElement $parent, string $name, ?string $value = null): DOMElement
    {
        $element = $parent->ownerDocument->createElementNS(DpsBuilder::NS, $name);
        if ($value !== null) {
            $element->appendChild($parent->ownerDocument->createTextNode($value));
        }
        $parent->appendChild($element);

        return $element;
    }
}
