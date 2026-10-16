<?php

namespace App\Modules\Invoices\Services\Danfse;

use App\Modules\Invoices\Models\Invoice;
use LogicException;

/**
 * DANFSe em PDF de uma nota. Gerado sob demanda a partir do XML guardado, então uma nota
 * cancelada passa a sair com a marca d'água sem nada a regerar.
 */
class DanfseGenerator
{
    public function __construct(protected DanfseRenderer $renderer) {}

    public function forInvoice(Invoice $invoice): string
    {
        if (! $invoice->hasNfseXml()) {
            throw new LogicException('A NFS-e ainda não foi gerada; não há DANFSe.');
        }

        return $this->renderer->render(DanfseData::fromNfseXml($invoice->nfse_xml, cancelled: $invoice->isCancelled()));
    }

    public function filename(Invoice $invoice): string
    {
        return 'danfse-'.($invoice->access_key ?? $invoice->getKey()).'.pdf';
    }
}
