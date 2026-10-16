<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TenantInvitation $invitation,
        public Tenant $tenant,
        public ?User $inviter,
        public string $acceptUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->tenant->name . ' convidou você para o workspace',
            to: [$this->invitation->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.tenant.invitation',
            with: [
                'inviteeEmail' => $this->invitation->email,
                'inviterName' => $this->inviter?->name ?? 'Um administrador',
                'tenantName' => $this->tenant->name,
                'role' => $this->invitation->role ?? 'member',
                'roleLabel' => $this->roleLabel($this->invitation->role),
                'acceptUrl' => $this->acceptUrl,
                'expiresAt' => $this->invitation->expires_at,
                'primaryColor' => $this->resolvePrimaryColor(),
                'logoUrl' => $this->resolveLogoUrl(),
            ],
        );
    }

    protected function roleLabel(?string $role): string
    {
        return match ($role) {
            'owner' => 'Proprietário',
            'admin' => 'Administrador',
            'member' => 'Membro',
            default => 'Membro',
        };
    }

    protected function resolvePrimaryColor(): string
    {
        $branding = $this->tenant->branding ?? [];
        $color = $branding['primary_color'] ?? '#4F46E5';

        if (! is_string($color) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return '#4F46E5';
        }

        return $color;
    }

    protected function resolveLogoUrl(): ?string
    {
        $branding = $this->tenant->branding ?? [];
        $url = $branding['logo_url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    public static function formatExpiry(?CarbonInterface $date): string
    {
        if ($date === null) {
            return '-';
        }
        return $date->locale('pt_BR')->isoFormat('D [de] MMMM [de] YYYY [às] HH:mm');
    }
}
