<?php

namespace App\Modules\Signatures\Mail;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Signatures\Models\Document;
use App\Modules\Signatures\Models\DocumentSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public DocumentSigner $signer,
        public Document $document,
        public ?Tenant $tenant = null,
        public ?User $sender = null,
    ) {}

    public function envelope(): Envelope
    {
        $tenantName = $this->tenant?->name;
        $prefix = $tenantName ? "{$tenantName} · " : '';

        return new Envelope(
            subject: $prefix . "{$this->document->name} aguarda sua assinatura",
            to: [$this->signer->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.signatures.document-ready',
            with: [
                'signer' => $this->signer,
                'document' => $this->document,
                'tenantName' => $this->tenant?->name,
                'senderName' => $this->sender?->name,
                'signUrl' => route('public-sign.show', ['token' => $this->signer->token]),
                'primaryColor' => $this->resolvePrimaryColor(),
                'logoUrl' => $this->resolveLogoUrl(),
                'expiresAt' => $this->document->expires_at,
            ],
        );
    }

    protected function resolvePrimaryColor(): string
    {
        $branding = $this->tenant?->branding ?? [];
        $color = $branding['primary_color'] ?? '#4F46E5';

        if (! is_string($color) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return '#4F46E5';
        }

        return $color;
    }

    protected function resolveLogoUrl(): ?string
    {
        $branding = $this->tenant?->branding ?? [];
        $url = $branding['logo_url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }
}
