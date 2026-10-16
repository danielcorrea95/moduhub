<?php

namespace App\Modules\Signatures\Mail;

use App\Models\Tenant;
use App\Modules\Signatures\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Cópia do documento assinado para cada envolvido, enviada quando a última pessoa assina.
 */
class DocumentCompletedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Acima disso o PDF vai só como link: o base64 do anexo cresce ~33% e muitos
     * provedores recusam mensagens acima de 20–25 MB.
     */
    public const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;

    public function __construct(
        public Document $document,
        public string $recipientName,
        public string $recipientEmail,
        public ?Tenant $tenant = null,
    ) {}

    public function envelope(): Envelope
    {
        $prefix = $this->tenant?->name ? "{$this->tenant->name} · " : '';

        return new Envelope(
            subject: $prefix . "{$this->document->name} foi assinado por todos",
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
                'attached' => $this->shouldAttach(),
                'sealed' => $this->document->isSealed(),
                'completedAt' => $this->document->completed_at?->copy()->setTimezone(config('signatures.report_timezone')),
                'downloadUrl' => route('verify.download', $short),
                'verifyUrl' => route('verify.show', $short),
                'primaryColor' => $this->resolvePrimaryColor(),
                'logoUrl' => $this->resolveLogoUrl(),
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        if (! $this->shouldAttach()) {
            return [];
        }

        return [
            Attachment::fromStorageDisk($this->document->file_disk, $this->document->final_file_path)
                ->as($this->attachmentName())
                ->withMime('application/pdf'),
        ];
    }

    protected function shouldAttach(): bool
    {
        $path = $this->document->final_file_path;
        $disk = Storage::disk($this->document->file_disk);

        return $path !== null && $disk->exists($path) && $disk->size($path) <= self::MAX_ATTACHMENT_BYTES;
    }

    protected function attachmentName(): string
    {
        $slug = Str::slug($this->document->name ?: 'documento');

        return ($slug !== '' ? $slug : 'documento') . '-assinado.pdf';
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
