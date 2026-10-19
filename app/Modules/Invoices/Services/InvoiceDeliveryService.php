<?php

namespace App\Modules\Invoices\Services;

use App\Modules\Invoices\Jobs\DeliverInvoiceByEmail;
use App\Modules\Invoices\Mail\InvoiceMail;
use App\Modules\Invoices\Models\Invoice;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Envio da NFS-e por e-mail ao tomador (ou a quem o emitente indicar), sempre pela fila.
 */
class InvoiceDeliveryService
{
    /** Envio automático depois da emissão, quando a nota pediu e o tomador tem e-mail. */
    public function afterIssued(Invoice $invoice): void
    {
        $email = $invoice->taker['email'] ?? null;
        if ($invoice->notify_taker && is_string($email) && $email !== '') {
            DeliverInvoiceByEmail::dispatch($invoice->getKey(), [$email], InvoiceMail::KIND_ISSUED)->afterCommit();
        }
    }

    /** Quem recebeu a nota é avisado do cancelamento ou da substituição. */
    public function afterCancelled(Invoice $invoice): void
    {
        $email = $invoice->taker['email'] ?? null;
        if ($invoice->taker_emailed_at !== null && is_string($email) && $email !== '') {
            DeliverInvoiceByEmail::dispatch($invoice->getKey(), [$email], InvoiceMail::KIND_CANCELLED)->afterCommit();
        }
    }

    /**
     * Reenvio manual, pelo painel ou pela API. Sem endereços, vai para o e-mail do tomador.
     *
     * @param  list<string>  $emails
     * @return list<string> os endereços para onde a nota vai
     */
    public function resend(Invoice $invoice, array $emails = []): array
    {
        if (! $invoice->hasNfseXml()) {
            throw ValidationException::withMessages(['emails' => 'A NFS-e ainda não foi gerada.']);
        }

        $emails = $emails ?: array_filter([(string) ($invoice->taker['email'] ?? '')]);
        if ($emails === []) {
            throw ValidationException::withMessages(['emails' => 'O tomador não tem e-mail cadastrado. Informe para onde enviar.']);
        }

        $kind = $invoice->isIssued() ? InvoiceMail::KIND_ISSUED : InvoiceMail::KIND_CANCELLED;
        DeliverInvoiceByEmail::dispatch($invoice->getKey(), array_values($emails), $kind);

        return array_values($emails);
    }

    /**
     * Executado pelo job. Roda fora de qualquer sessão: a nota é buscada sem o scope do tenant.
     *
     * @param  list<string>  $emails
     */
    public function send(string $invoiceId, array $emails, string $kind): void
    {
        $invoice = Invoice::query()->withoutTenantScope()->with(['issuer' => fn ($q) => $q->withoutTenantScope()])->find($invoiceId);
        $tenant = $invoice?->tenant()->withoutGlobalScopes()->first();
        if ($invoice === null || $tenant === null) {
            return;
        }

        try {
            Mail::to($emails)->send(new InvoiceMail($invoice, $tenant, $kind));
        } catch (Throwable $e) {
            $invoice->forceFill(['taker_email_error' => mb_substr($e->getMessage(), 0, 255)])->save();

            throw $e;
        }

        if ($kind === InvoiceMail::KIND_ISSUED) {
            $invoice->forceFill(['taker_emailed_at' => now(), 'taker_email_error' => null])->save();
        }
    }
}
