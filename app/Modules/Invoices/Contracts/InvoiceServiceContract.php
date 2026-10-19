<?php

namespace App\Modules\Invoices\Contracts;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Invoices\Models\Invoice;
use Illuminate\Validation\ValidationException;

interface InvoiceServiceContract
{
    /**
     * Reserva o número da DPS, assina e envia à Sefin Nacional. Devolve a nota emitida, rejeitada
     * ou pendente (sem resposta). Com `external_id` repetido devolve a nota existente sem reenviar.
     *
     * @throws ValidationException emitente incompleto ou DPS fora do leiaute.
     */
    public function issue(Tenant $tenant, ?User $creator, array $data): Invoice;

    /**
     * Emite uma NFS-e substituta (DPS com o grupo subst). Quando a Sefin gera a nova nota, a original
     * fica como substituída. Sem idempotência por external_id: a substituta costuma repetir o do pedido.
     *
     * @throws ValidationException original não emitida ou substituição já pendente.
     */
    public function replace(Invoice $original, Tenant $tenant, ?User $creator, array $data, string $reasonCode, ?string $reason): Invoice;

    /** Resolve uma nota pendente: consulta a DPS na Sefin e, se não houver NFS-e, reenvia. */
    public function sync(Invoice $invoice): Invoice;

    /** @throws ValidationException quando a Sefin recusa o cancelamento. */
    public function cancel(Invoice $invoice, int $reasonCode, string $reason): Invoice;
}
