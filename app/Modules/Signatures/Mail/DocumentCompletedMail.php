<?php

namespace App\Modules\Signatures\Mail;

use App\Models\Tenant;
use App\Modules\Mailing\Services\TenantSender;
use App\Modules\Signatures\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso para cada envolvido quando a última pessoa assina, com o link da cópia final.
 *
 * O PDF vai só como link, sem anexo: o SES cobra por GB de anexo e PDF anexado de
 * remetente novo pesa contra a entrega no Outlook/Hotmail.
 */
class DocumentCompletedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Document $document,
        public string $recipientName,
        public string $recipientEmail,
        public ?Tenant $tenant = null,
    ) {}

    public function envelope(): Envelope
    {
        $prefix = $this->tenant?->name ? "{$this->tenant->name} · " : '';

        $sender = app(TenantSender::class);

        return new Envelope(
            from: $sender->from($this->tenant),
            replyTo: $sender->replyTo($this->tenant),
            subject: $prefix."{$this->document->name} foi assinado por todos",
        );
    }

    public function content(): Content
    {
        $short = strtoupper(substr($this->document->getKey(), -12));

        return new Content(
            view: 'mail.signatures.document-completed',
            with: [
                'document' => $this->document,
                'recipientName' => $this->recipientName,
                'recipientEmail' => $this->recipientEmail,
                'tenantName' => $this->tenant?->name,
                'signers' => $this->document->signers()->withoutTenantScope()->orderBy('order')->get(),
                'sealed' => $this->document->isSealed(),
                'completedAt' => $this->document->completed_at?->copy()->setTimezone(config('signatures.report_timezone')),
                'downloadUrl' => route('verify.download', $short),
                'verifyUrl' => route('verify.show', $short),
                'primaryColor' => $this->resolvePrimaryColor(),
                'logoUrl' => $this->resolveLogoUrl(),
            ],
        );
    }

    protected function resolvePrimaryColor(): string
    {
        $color = ($this->tenant?->branding ?? [])['primary_color'] ?? '#2338B8';

        return is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#2338B8';
    }

    protected function resolveLogoUrl(): ?string
    {
        $url = ($this->tenant?->branding ?? [])['logo_url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }
}
