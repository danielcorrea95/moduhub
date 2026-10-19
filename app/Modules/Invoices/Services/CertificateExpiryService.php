<?php

namespace App\Modules\Invoices\Services;

use App\Models\Tenant;
use App\Modules\Invoices\Mail\CertificateExpiringMail;
use App\Modules\Invoices\Models\Issuer;
use App\Platform\Contracts\MemberRepositoryContract;
use App\Platform\Contracts\WebhookDispatcherContract;
use App\Platform\Security\CertificateExpiry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Avisa o workspace antes de o certificado do emitente vencer: 30, 15, 7 e 1 dia antes, e no dia em que
 * venceu. Cada marco sai uma vez só por certificado; um certificado novo zera a contagem.
 */
class CertificateExpiryService
{
    public function __construct(
        protected MemberRepositoryContract $members,
        protected CertificateAuditService $audit,
        protected WebhookDispatcherContract $webhooks,
    ) {}

    /**
     * Envia o aviso se o certificado chegou a um marco ainda não avisado.
     *
     * @return int|null dias até o vencimento quando avisou, null quando não havia o que avisar
     */
    public function notifyIfDue(Issuer $issuer): ?int
    {
        if (! $issuer->hasCertificate() || $issuer->certificate_valid_until === null) {
            return null;
        }

        $daysLeft = CertificateExpiry::daysLeft($issuer->certificate_valid_until);
        $notice = CertificateExpiry::noticeFor($daysLeft);

        if (! CertificateExpiry::isDue($notice, $issuer->certificate_expiry_notice_days)) {
            return null;
        }

        $tenant = $issuer->tenant;
        $recipients = $this->recipients($tenant, $issuer);

        foreach ($recipients as $email) {
            Mail::to($email)->send(new CertificateExpiringMail($issuer, $tenant, $daysLeft));
        }

        DB::transaction(function () use ($issuer, $tenant, $notice, $daysLeft, $recipients) {
            $issuer->forceFill(['certificate_expiry_notice_days' => $notice])->save();
            $this->audit->expiryNoticeSent($issuer, $daysLeft, $recipients);
            $this->webhooks->fire($tenant, 'invoice.certificate_expiring', [
                'issuer_id' => $issuer->getKey(),
                'cnpj' => $issuer->cnpj,
                'certificate_fingerprint' => $issuer->certificate_fingerprint,
                'valid_until' => $issuer->certificate_valid_until->toIso8601String(),
                'days_left' => $daysLeft,
                'expired' => $daysLeft === 0,
            ]);
        });

        return $daysLeft;
    }

    /**
     * Donos e administradores (quem pode trocar o certificado) e o e-mail fiscal do emitente, que costuma
     * ser o contador.
     *
     * @return list<string>
     */
    protected function recipients(Tenant $tenant, Issuer $issuer): array
    {
        $emails = $this->members->managers($tenant)->pluck('email')->push($issuer->email);

        return $emails->filter()->map(fn ($email) => mb_strtolower((string) $email))->unique()->values()->all();
    }
}
