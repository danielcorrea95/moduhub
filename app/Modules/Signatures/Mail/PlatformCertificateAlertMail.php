<?php

namespace App\Modules\Signatures\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Alerta interno, para quem opera a plataforma: texto simples basta. */
class PlatformCertificateAlertMail extends Mailable
{
    public function __construct(
        public string $alert,
        public string $alertSubject = '[Moduhub] Certificado da plataforma precisa de atenção',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->alertSubject);
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>'.e($this->alert).'</p><p>'.e(config('app.url')).'</p>');
    }
}
