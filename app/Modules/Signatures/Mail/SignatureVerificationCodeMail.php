<?php

namespace App\Modules\Signatures\Mail;

use App\Models\Tenant;
use App\Modules\Signatures\Models\DocumentSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SignatureVerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public DocumentSigner $signer,
        public string $code,
        public string $documentName,
        public ?Tenant $tenant = null,
    ) {}

    public function envelope(): Envelope
    {
        $tenantName = $this->tenant?->name;
        $prefix = $tenantName ? "{$tenantName} · " : '';

        return new Envelope(
            subject: $prefix . "Seu código para assinar: {$this->documentName}",
            to: [$this->signer->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.signatures.verification-code',
            with: [
                'signer' => $this->signer,
                'code' => $this->code,
                'documentName' => $this->documentName,
                'tenantName' => $this->tenant?->name,
                'signUrl' => route('public-sign.show', ['token' => $this->signer->token]),
                'primaryColor' => $this->resolvePrimaryColor(),
                'logoUrl' => $this->resolveLogoUrl(),
                'ttlMinutes' => 15,
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
