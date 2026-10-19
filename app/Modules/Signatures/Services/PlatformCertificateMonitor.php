<?php

namespace App\Modules\Signatures\Services;

use App\Modules\Signatures\Mail\PlatformCertificateAlertMail;
use App\Platform\Security\CertificateExpiry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Vigia o e-CNPJ da própria Moduhub, que sela todo documento concluído e autentica a varredura de
 * convênios no ADN. Se ele vence, os documentos passam a sair sem selo em silêncio: o aviso precisa
 * chegar antes, para quem opera a plataforma.
 */
class PlatformCertificateMonitor
{
    public function __construct(protected PlatformSealService $seal) {}

    /** @return string|null a mensagem de alerta, quando houve */
    public function check(): ?string
    {
        // Sem senha o selo está desligado de propósito (dev, testes): não há o que vigiar.
        if (! $this->seal->isConfigured()) {
            return null;
        }

        try {
            $certificate = $this->seal->certificate();
        } catch (Throwable $e) {
            // Vencido também cai aqui: a leitura recusa certificado fora da validade. Avisa todo dia até resolver.
            return $this->alert(
                'Certificado da plataforma não abre: os documentos concluídos estão saindo SEM o selo ICP-Brasil. '.$e->getMessage(),
                critical: true,
            );
        }

        $daysLeft = CertificateExpiry::daysLeft($certificate->validTo);
        $notice = CertificateExpiry::noticeFor($daysLeft);
        // Por número de série: renovar o certificado começa a contagem de novo.
        $key = "platform-certificate-expiry-notice:{$certificate->serial}";

        if (! CertificateExpiry::isDue($notice, Cache::get($key))) {
            return null;
        }

        Cache::forever($key, $notice);

        return $this->alert(
            "Certificado da plataforma ({$certificate->subjectCn}) vence em {$daysLeft} dia(s), em {$certificate->validTo->format('d/m/Y')}. "
            .'Renove o e-CNPJ A1 e troque o arquivo e a senha no servidor (SIGNATURES_PLATFORM_CERT_PATH e SIGNATURES_PLATFORM_CERT_PASSWORD).',
            critical: $daysLeft <= 7,
        );
    }

    protected function alert(string $message, bool $critical): string
    {
        $critical ? Log::critical($message) : Log::warning($message);

        foreach ((array) config('signatures.seal.alert_emails') as $email) {
            Mail::to($email)->send(new PlatformCertificateAlertMail($message));
        }

        return $message;
    }
}
