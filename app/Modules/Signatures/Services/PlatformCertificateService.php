<?php

namespace App\Modules\Signatures\Services;

use App\Modules\Signatures\Contracts\PlatformCertificateRepositoryContract;
use App\Modules\Signatures\Mail\PlatformCertificateAlertMail;
use App\Modules\Signatures\Models\PlatformCertificate;
use App\Platform\Audit\AuditActorResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * e-CNPJ da Moduhub: o enviado pelo admin vale sobre o do servidor (SIGNATURES_PLATFORM_CERT_PATH),
 * que fica como reserva. Quem entra numa conta de admin consegue trocar ou tirar o selo de todos os
 * documentos, então só entra certificado ICP-Brasil com o CNPJ da Moduhub e toda troca avisa a operação na hora.
 */
class PlatformCertificateService
{
    public function __construct(
        protected PlatformCertificateRepositoryContract $certificates,
        protected IcpCertificateService $icp,
        protected IcpChainValidator $chain,
        protected AuditActorResolver $actors,
    ) {}

    public function isConfigured(): bool
    {
        return $this->certificates->current() !== null || $this->hasServerCertificate();
    }

    /** Se há um certificado configurado no servidor, usado quando o painel não tem nenhum. */
    public function hasServerCertificate(): bool
    {
        return filled(config('signatures.seal.certificate_password'));
    }

    /** CNPJ da Moduhub configurado no servidor, só dígitos, ou null se faltar ou estiver incompleto. */
    public function platformCnpj(): ?string
    {
        $cnpj = preg_replace('/\D+/', '', (string) config('signatures.seal.platform_cnpj')) ?? '';

        return strlen($cnpj) === 14 ? $cnpj : null;
    }

    public function pkcs12(): ?PlatformPkcs12
    {
        $current = $this->certificates->current();

        if ($current !== null) {
            $binary = base64_decode((string) $current->pfx, true);

            return $binary === false ? null : new PlatformPkcs12($binary, (string) $current->password, PlatformPkcs12::ORIGIN_PANEL);
        }

        $path = (string) config('signatures.seal.certificate_path');
        $password = config('signatures.seal.certificate_password');

        if (! filled($password) || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $binary = file_get_contents($path);

        return $binary === false ? null : new PlatformPkcs12($binary, (string) $password, PlatformPkcs12::ORIGIN_SERVER);
    }

    public function install(string $pkcs12Binary, #[\SensitiveParameter] string $password): PlatformCertificate
    {
        $certificate = $this->icp->parse($pkcs12Binary, $password);
        $this->assertBelongsToPlatform($certificate);
        // O CNPJ no nome não prova nada sozinho: quem invade uma conta de admin gera um autoassinado com ele.
        $this->chain->assertTrusted($certificate);

        $actor = $this->actors->current();

        $installed = DB::transaction(function () use ($certificate, $pkcs12Binary, $password, $actor) {
            $this->retireCurrent(PlatformCertificate::RETIRED_REPLACED);

            return PlatformCertificate::create([
                'pfx' => base64_encode($pkcs12Binary),
                'password' => $password,
                'subject' => $certificate->subjectCn,
                'cnpj' => $certificate->cnpj,
                'issuer' => $certificate->issuerCn,
                'serial' => $certificate->serial,
                'fingerprint' => (string) openssl_x509_fingerprint($certificate->certPem, 'sha256'),
                'valid_from' => $certificate->validFrom,
                'valid_until' => $certificate->validTo,
                'uploaded_by_id' => $actor->id,
                'uploaded_by_label' => $actor->label,
                'uploaded_ip' => $actor->ipAddress,
            ]);
        });

        $this->notify(sprintf(
            'O certificado da plataforma foi trocado pelo painel por %s (IP %s). Novo: %s, válido até %s, impressão digital %s.',
            $actor->label ?? 'Sistema',
            $actor->ipAddress ?? 'desconhecido',
            $installed->subject,
            $installed->valid_until->format('d/m/Y'),
            $installed->fingerprint,
        ));

        return $installed;
    }

    /** Volta para o certificado do servidor, se houver; sem ele os documentos passam a sair sem selo. */
    public function remove(): void
    {
        $actor = $this->actors->current();
        $removed = DB::transaction(fn () => $this->retireCurrent(PlatformCertificate::RETIRED_REMOVED));

        if ($removed === null) {
            return;
        }

        $fallback = $this->hasServerCertificate()
            ? 'O selo volta a usar o certificado do servidor.'
            : 'Não há certificado no servidor: os documentos concluídos passam a sair SEM o selo ICP-Brasil.';

        $this->notify(sprintf(
            'O certificado da plataforma (%s) foi removido pelo painel por %s (IP %s). %s',
            $removed->subject,
            $actor->label ?? 'Sistema',
            $actor->ipAddress ?? 'desconhecido',
            $fallback,
        ));
    }

    protected function retireCurrent(string $reason): ?PlatformCertificate
    {
        $current = $this->certificates->current();

        if ($current === null) {
            return null;
        }

        $actor = $this->actors->current();

        // O .pfx aposentado não tem mais uso: apagar reduz o que um vazamento do banco expõe.
        $current->forceFill([
            'pfx' => null,
            'password' => null,
            'retired_at' => now(),
            'retired_reason' => $reason,
            'retired_by_id' => $actor->id,
            'retired_by_label' => $actor->label,
            'retired_ip' => $actor->ipAddress,
        ])->save();

        return $current;
    }

    protected function assertBelongsToPlatform(IcpCertificate $certificate): void
    {
        $platformCnpj = $this->platformCnpj();

        if ($platformCnpj === null) {
            throw ValidationException::withMessages([
                'certificate' => 'Configure o CNPJ da Moduhub no servidor (SIGNATURES_PLATFORM_CNPJ) antes de enviar o certificado pelo painel.',
            ]);
        }

        if ($certificate->cnpj === null) {
            throw ValidationException::withMessages([
                'certificate' => 'Este certificado não é um e-CNPJ: não encontramos o CNPJ do titular.',
            ]);
        }

        // Matriz ou filial servem: a comparação é pela raiz, como a Sefin faz com o emitente.
        if (substr($certificate->cnpj, 0, 8) !== substr($platformCnpj, 0, 8)) {
            throw ValidationException::withMessages([
                'certificate' => "O certificado é do CNPJ {$certificate->cnpj}, que não é da Moduhub.",
            ]);
        }

        $parsed = @openssl_x509_parse($certificate->certPem);
        $basicConstraints = is_array($parsed) ? (string) ($parsed['extensions']['basicConstraints'] ?? '') : '';

        if (str_contains(strtoupper($basicConstraints), 'CA:TRUE')) {
            throw ValidationException::withMessages([
                'certificate' => 'Este é um certificado de Autoridade Certificadora, não do titular.',
            ]);
        }
    }

    protected function notify(string $message): void
    {
        Log::warning($message);

        foreach ((array) config('signatures.seal.alert_emails') as $email) {
            try {
                Mail::to($email)->send(new PlatformCertificateAlertMail($message, alertSubject:'[Moduhub] Certificado da plataforma alterado pelo painel'));
            } catch (Throwable $e) {
                // A troca já foi gravada; o aviso que falhou fica no log, que também registra a troca.
                Log::error('Falha ao avisar a troca do certificado da plataforma', ['email' => $email, 'error' => $e->getMessage()]);
            }
        }
    }
}
