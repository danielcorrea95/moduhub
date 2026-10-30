<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Workspace criado pela equipe da Moduhub. E-mail da conta: sai sempre do remetente da Moduhub. */
class CustomerWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param  string|null  $setPasswordUrl  nulo quando o dono já tinha conta */
    public function __construct(
        public Tenant $tenant,
        public User $owner,
        public ?string $setPasswordUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Seu workspace {$this->tenant->name} na Moduhub está pronto",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.tenant.welcome',
            with: [
                'ownerName' => $this->owner->name,
                'ownerEmail' => $this->owner->email,
                'tenantName' => $this->tenant->name,
                'actionUrl' => $this->setPasswordUrl ?? route('login'),
                'actionLabel' => $this->setPasswordUrl !== null ? 'Criar minha senha' : 'Entrar na Moduhub',
                'newAccount' => $this->setPasswordUrl !== null,
                'linkMinutes' => (int) config('auth.passwords.users.expire'),
            ],
        );
    }
}
