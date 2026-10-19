<?php

namespace App\Modules\Invoices\Services;

use App\Modules\Invoices\Contracts\CertificateEventRepositoryContract;
use App\Modules\Invoices\Models\CertificateEvent;
use App\Modules\Invoices\Models\Issuer;
use App\Platform\Audit\AuditActorResolver;

/**
 * Registra o ciclo de vida do certificado do emitente e cada uso da chave privada, para o cliente
 * poder responder "quem usou meu certificado, quando e para quê".
 */
class CertificateAuditService
{
    public function __construct(
        protected CertificateEventRepositoryContract $events,
        protected AuditActorResolver $actors,
    ) {}

    public function uploaded(Issuer $issuer, ?string $previousFingerprint): CertificateEvent
    {
        $replaced = $previousFingerprint !== null && $previousFingerprint !== $issuer->certificate_fingerprint;

        return $this->record($issuer, $replaced ? CertificateEvent::TYPE_REPLACED : CertificateEvent::TYPE_UPLOADED, data: array_filter([
            'subject' => $issuer->certificate_subject,
            'valid_until' => $issuer->certificate_valid_until?->toIso8601String(),
            'previous_fingerprint' => $replaced ? $previousFingerprint : null,
        ]));
    }

    /** Chamado antes de apagar: o evento guarda qual certificado saiu. */
    public function removed(Issuer $issuer): CertificateEvent
    {
        return $this->record($issuer, CertificateEvent::TYPE_REMOVED, data: ['subject' => $issuer->certificate_subject]);
    }

    /** @param  array<string, mixed>  $data  referência do que foi assinado ou consultado (nota, chave de acesso, operação) */
    public function used(Issuer $issuer, string $purpose, array $data = []): CertificateEvent
    {
        return $this->record($issuer, CertificateEvent::TYPE_USED, $purpose, $data);
    }

    /** @param  list<string>  $recipients */
    public function expiryNoticeSent(Issuer $issuer, int $daysLeft, array $recipients): CertificateEvent
    {
        return $this->record($issuer, CertificateEvent::TYPE_EXPIRY_NOTICE, data: [
            'days_left' => $daysLeft,
            'valid_until' => $issuer->certificate_valid_until?->toIso8601String(),
            'recipients' => $recipients,
        ]);
    }

    /** @param  array<string, mixed>  $data */
    protected function record(Issuer $issuer, string $type, ?string $purpose = null, array $data = []): CertificateEvent
    {
        return $this->events->record([
            'tenant_id' => $issuer->tenant_id,
            'issuer_id' => $issuer->getKey(),
            'type' => $type,
            'purpose' => $purpose,
            'certificate_fingerprint' => $issuer->certificate_fingerprint,
            'certificate_serial' => $issuer->certificate_serial,
            ...$this->actors->current()->toAttributes(),
            'data' => $data ?: null,
            'created_at' => now(),
        ]);
    }
}
