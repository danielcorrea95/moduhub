<?php

namespace App\Modules\Invoices\Contracts;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Invoices\Models\Invoice;

interface InvoiceServiceContract
{
    /**
     * Reserva o número da DPS, assina e envia à Sefin Nacional. Devolve a nota emitida, rejeitada
     * ou pendente (sem resposta). Com `external_id` repetido devolve a nota existente sem reenviar.
     *
     * @throws \Illuminate\Validation\ValidationException emitente incompleto ou DPS fora do leiaute.
     */
    public function issue(Tenant $tenant, ?User $creator, array $data): Invoice;

    /** Resolve uma nota pendente: consulta a DPS na Sefin e, se não houver NFS-e, reenvia. */
    public function sync(Invoice $invoice): Invoice;

    /** @throws \Illuminate\Validation\ValidationException quando a Sefin recusa o cancelamento. */
    public function cancel(Invoice $invoice, int $reasonCode, string $reason): Invoice;
}
