<?php

namespace App\Modules\Invoices\Services\Nfse;

use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\Issuer;
use DOMDocument;
use DOMElement;

/**
 * Pedido de registro do evento 101101 (cancelamento de NFS-e), schema pedRegEvento v1.01.
 */
class CancellationBuilder
{
    public const EVENT_CODE = '101101';

    public function build(Invoice $invoice, Issuer $issuer, int $reasonCode, string $reason): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $root = $doc->createElementNS(DpsBuilder::NS, 'pedRegEvento');
        $root->setAttribute('versao', (string) config('invoices.layout_version'));
        $doc->appendChild($root);

        $inf = $this->el($root, 'infPedReg');
        $inf->setAttribute('Id', 'PRE'.$invoice->access_key.self::EVENT_CODE);

        $this->el($inf, 'tpAmb', $issuer->isProduction() ? '1' : '2');
        $this->el($inf, 'verAplic', (string) config('invoices.app_version'));
        $this->el($inf, 'dhEvento', now()->subMinute()->setTimezone('America/Sao_Paulo')->format('Y-m-d\TH:i:sP'));
        $this->el($inf, 'CNPJAutor', $issuer->cnpj);
        $this->el($inf, 'chNFSe', (string) $invoice->access_key);

        $event = $this->el($inf, 'e'.self::EVENT_CODE);
        $this->el($event, 'xDesc', 'Cancelamento de NFS-e');
        $this->el($event, 'cMotivo', (string) $reasonCode);
        $this->el($event, 'xMotivo', NfseText::clean($reason) ?? '');

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
