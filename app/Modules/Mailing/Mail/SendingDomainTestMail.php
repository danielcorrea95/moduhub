<?php

namespace App\Modules\Mailing\Mail;

use App\Models\Tenant;
use App\Modules\Mailing\Services\TenantSender;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sai pelo mesmo remetente dos convites de assinatura e das NFS-e, para o cliente ver o que o
 * destinatário vai receber.
 */
class SendingDomainTestMail extends Mailable
{
    public function __construct(
        public Tenant $tenant,
    ) {}

    public function envelope(): Envelope
    {
        $sender = app(TenantSender::class);

        return new Envelope(
            from: $sender->from($this->tenant),
            replyTo: $sender->replyTo($this->tenant),
            subject: "{$this->tenant->name} · e-mail de teste",
        );
    }

    public function content(): Content
    {
        $from = app(TenantSender::class)->from($this->tenant);
        $color = ($this->tenant->branding ?? [])['primary_color'] ?? '#2338B8';

        return new Content(
            view: 'mail.mailing.sending-domain-test',
            with: [
                'tenantName' => $this->tenant->name,
                'fromAddress' => $from?->address ?? config('mail.from.address'),
                'usesOwnDomain' => $from !== null,
                'primaryColor' => is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#2338B8',
                'settingsUrl' => route('sending-domain.show'),
            ],
        );
    }
}
