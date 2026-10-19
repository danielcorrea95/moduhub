<?php

namespace App\Modules\Invoices\Mail;

use App\Models\Tenant;
use App\Modules\Invoices\Models\Issuer;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CertificateExpiringMail extends Mailable
{
    public function __construct(
        public Issuer $issuer,
        public Tenant $tenant,
        public int $daysLeft,
    ) {}

    public function envelope(): Envelope
    {
        $name = $this->issuer->trade_name ?: $this->issuer->legal_name;

        return new Envelope(subject: match (true) {
            $this->daysLeft === 0 => "{$name} · O certificado digital venceu e as notas pararam",
            $this->daysLeft === 1 => "{$name} · O certificado digital vence amanhã",
            default => "{$name} · O certificado digital vence em {$this->daysLeft} dias",
        });
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.invoices.certificate-expiring',
            with: [
                'issuerName' => $this->issuer->trade_name ?: $this->issuer->legal_name,
                'workspaceName' => $this->tenant->name,
                'daysLeft' => $this->daysLeft,
                'expired' => $this->daysLeft === 0,
                'validUntil' => $this->issuer->certificate_valid_until?->copy()->setTimezone(config('signatures.report_timezone', 'America/Sao_Paulo')),
                'subject' => $this->issuer->certificate_subject,
                'issuerUrl' => route('invoices.issuer.edit'),
            ],
        );
    }
}
