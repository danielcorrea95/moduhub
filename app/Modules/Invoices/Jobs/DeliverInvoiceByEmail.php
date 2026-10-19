<?php

namespace App\Modules\Invoices\Jobs;

use App\Modules\Invoices\Services\InvoiceDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class DeliverInvoiceByEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    /** @param  list<string>  $emails */
    public function __construct(
        public string $invoiceId,
        public array $emails,
        public string $kind,
    ) {}

    public function handle(InvoiceDeliveryService $delivery): void
    {
        $delivery->send($this->invoiceId, $this->emails, $this->kind);
    }
}
