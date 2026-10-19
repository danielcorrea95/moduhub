<?php

namespace App\Modules\Invoices\Mail;

use App\Models\Tenant;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Services\Danfse\DanfseGenerator;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * NFS-e para o tomador: a nota emitida (DANFSe + XML) ou o aviso de cancelamento/substituição.
 * Enviada de dentro de um job, então não é ShouldQueue.
 */
class InvoiceMail extends Mailable
{
    public const KIND_ISSUED = 'issued';

    public const KIND_CANCELLED = 'cancelled';

    public function __construct(
        public Invoice $invoice,
        public Tenant $tenant,
        public string $kind = self::KIND_ISSUED,
    ) {}

    public function envelope(): Envelope
    {
        $issuer = $this->invoice->issuer;
        $from = $issuer->trade_name ?: $issuer->legal_name;
        $number = $this->invoice->nfse_number;

        return new Envelope(
            subject: match ($this->kind) {
                self::KIND_CANCELLED => $this->invoice->isReplaced()
                    ? "{$from} · NFS-e nº {$number} substituída"
                    : "{$from} · NFS-e nº {$number} cancelada",
                default => "{$from} · NFS-e nº {$number}",
            },
            // Resposta vai para o emitente, não para a plataforma.
            replyTo: $issuer->email ? [new Address($issuer->email, $from)] : [],
        );
    }

    public function content(): Content
    {
        $issuer = $this->invoice->issuer;

        return new Content(
            view: 'mail.invoices.invoice',
            with: [
                'invoice' => $this->invoice,
                'kind' => $this->kind,
                'issuerName' => $issuer->trade_name ?: $issuer->legal_name,
                'takerName' => $this->invoice->taker_name,
                'amount' => 'R$ '.number_format((float) $this->invoice->amount, 2, ',', '.'),
                'issuedAt' => $this->invoice->issued_at?->copy()->setTimezone('America/Sao_Paulo'),
                'restricted' => $this->invoice->environment === 'restricted',
                'consultUrl' => 'https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave='.$this->invoice->access_key,
                'primaryColor' => $this->primaryColor(),
                'logoUrl' => $this->logoUrl(),
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        if ($this->kind !== self::KIND_ISSUED || ! $this->invoice->hasNfseXml()) {
            return [];
        }

        $danfse = app(DanfseGenerator::class);

        return [
            Attachment::fromData(fn () => $danfse->forInvoice($this->invoice), $danfse->filename($this->invoice))->withMime('application/pdf'),
            Attachment::fromData(fn () => $this->invoice->nfse_xml, 'nfse-'.$this->invoice->access_key.'.xml')->withMime('application/xml'),
        ];
    }

    protected function primaryColor(): string
    {
        $color = ($this->tenant->branding ?? [])['primary_color'] ?? '#2338B8';

        return is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#2338B8';
    }

    protected function logoUrl(): ?string
    {
        $url = ($this->tenant->branding ?? [])['logo_url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }
}
