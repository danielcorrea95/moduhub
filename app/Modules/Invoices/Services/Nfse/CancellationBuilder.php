<?php

namespace App\Modules\Invoices\Services\Nfse;

use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\Issuer;
use DOMDocument;

/**
 * Pedido de registro do evento 101101 (cancelamento de NFS-e), schema pedRegEvento v1.01.
 */
class CancellationBuilder
{
    public const EVENT_CODE = EventRequestBuilder::CANCELLATION;

    public function __construct(protected EventRequestBuilder $events) {}

    public function build(Invoice $invoice, Issuer $issuer, int $reasonCode, string $reason): DOMDocument
    {
        return $this->events->build((string) $invoice->access_key, $issuer, self::EVENT_CODE, $reasonCode, $reason);
    }
}
