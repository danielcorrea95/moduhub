<?php

namespace App\Modules\Mailing\Services;

use App\Models\Tenant;
use App\Modules\Mailing\Contracts\EmailIdentityGatewayContract;
use App\Modules\Mailing\Contracts\SendingDomainRepositoryContract;
use App\Modules\Mailing\Exceptions\TestEmailFailed;
use App\Modules\Mailing\Mail\SendingDomainTestMail;
use App\Modules\Mailing\Models\SendingDomain;
use App\Modules\Mailing\Services\Identity\IdentityStatus;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SendingDomainService
{
    public function __construct(
        private SendingDomainRepositoryContract $domains,
        private EmailIdentityGatewayContract $identities,
        private CurrentTenant $current,
        private TenantSender $sender,
    ) {}

    /**
     * Cria a identidade no SES e guarda os registros que o cliente precisa publicar no DNS.
     *
     * @param  array{domain: string, from_name: string, from_local_part: string, reply_to?: ?string}  $data
     */
    public function register(Tenant $tenant, array $data): SendingDomain
    {
        $domain = $data['domain'];
        $identity = $this->identities->createDomainIdentity($domain, SendingDomain::MAIL_FROM_SUBDOMAIN.'.'.$domain);

        return $this->current->run($tenant, fn () => $this->domains->create([
            'tenant_id' => $tenant->getKey(),
            'domain' => $domain,
            'status' => $identity->verifiedForSending ? SendingDomain::STATUS_VERIFIED : SendingDomain::STATUS_PENDING,
            'dkim_tokens' => $identity->dkimTokens,
            'dkim_status' => $identity->dkimStatus,
            'mail_from_status' => $identity->mailFromStatus,
            'from_name' => $data['from_name'],
            'from_local_part' => $data['from_local_part'],
            'reply_to' => $data['reply_to'] ?? null,
            'verified_at' => $identity->verifiedForSending ? now() : null,
            'last_checked_at' => now(),
        ]));
    }

    /** @param  array{from_name: string, from_local_part: string, reply_to?: ?string}  $data */
    public function updateSender(SendingDomain $domain, array $data): SendingDomain
    {
        $domain->fill([
            'from_name' => $data['from_name'],
            'from_local_part' => $data['from_local_part'],
            'reply_to' => $data['reply_to'] ?? null,
        ]);

        return $this->domains->save($domain);
    }

    /**
     * Pergunta ao SES se o DNS está certo. Um domínio que deixa de verificar (registro apagado)
     * vira `failed`, e os e-mails voltam a sair pelo remetente da Moduhub.
     */
    public function checkVerification(SendingDomain $domain): SendingDomain
    {
        $identity = $this->identities->status($domain->domain);
        $wasVerified = $domain->isVerified();

        $domain->fill([
            'dkim_tokens' => $identity->dkimTokens ?: $domain->dkim_tokens,
            'dkim_status' => $identity->dkimStatus,
            'mail_from_status' => $identity->mailFromStatus,
            'last_checked_at' => now(),
        ]);

        if ($identity->verifiedForSending) {
            $domain->status = SendingDomain::STATUS_VERIFIED;
            $domain->verified_at ??= now();
        } elseif ($wasVerified || $identity->dkimStatus === IdentityStatus::FAILED) {
            $domain->status = SendingDomain::STATUS_FAILED;
        } else {
            $domain->status = SendingDomain::STATUS_PENDING;
        }

        if ($wasVerified && ! $domain->isVerified()) {
            Log::warning('E-mail: domínio de envio deixou de estar verificado; voltando ao remetente da plataforma.', [
                'tenant_id' => $domain->tenant_id,
                'domain' => $domain->domain,
                'dkim_status' => $identity->dkimStatus,
            ]);
        }

        return $this->domains->save($domain);
    }

    /** Confere todos os domínios. Roda sem tenant no contexto e entra em cada um. */
    public function checkAll(): int
    {
        $checked = 0;

        foreach ($this->domains->allForMonitoring() as $domain) {
            $this->current->run($domain->tenant, fn () => $this->checkVerification($domain));
            $checked++;
        }

        return $checked;
    }

    /**
     * Manda o e-mail de teste com o remetente que o workspace usa hoje (o domínio próprio, se
     * verificado, ou o da Moduhub). O conteúdo é fixo e a rota tem limite de envios, para o teste
     * não virar canal de disparo.
     *
     * @return string o endereço usado como remetente
     *
     * @throws TestEmailFailed
     */
    public function sendTest(Tenant $tenant, string $to): string
    {
        try {
            $this->current->run($tenant, fn () => Mail::to($to)->send(new SendingDomainTestMail($tenant)));
        } catch (TransportExceptionInterface $e) {
            report($e);

            throw TestEmailFailed::from($e);
        }

        return $this->sender->from($tenant)?->address ?? (string) config('mail.from.address');
    }

    public function remove(SendingDomain $domain): void
    {
        $this->identities->deleteDomainIdentity($domain->domain);
        $this->domains->delete($domain);
    }

    /**
     * Registros para o cliente publicar no DNS, com a situação de cada um.
     *
     * @return list<array{type: string, name: string, value: string, purpose: string, status: string}>
     */
    public function dnsRecords(SendingDomain $domain): array
    {
        $region = config('services.ses.region');
        $dkimStatus = $this->recordStatus($domain->dkim_status);
        $mailFromStatus = $this->recordStatus($domain->mail_from_status);

        $records = array_map(fn (string $token) => [
            'type' => 'CNAME',
            'name' => "{$token}._domainkey.{$domain->domain}",
            'value' => "{$token}.dkim.amazonses.com",
            'purpose' => 'dkim',
            'status' => $dkimStatus,
        ], $domain->dkim_tokens ?? []);

        $records[] = [
            'type' => 'MX',
            'name' => $domain->mailFromDomain(),
            'value' => "10 feedback-smtp.{$region}.amazonses.com",
            'purpose' => 'mail_from',
            'status' => $mailFromStatus,
        ];
        $records[] = [
            'type' => 'TXT',
            'name' => $domain->mailFromDomain(),
            'value' => 'v=spf1 include:amazonses.com ~all',
            'purpose' => 'mail_from',
            'status' => $mailFromStatus,
        ];
        // O SES não confere o DMARC; recomendado porque Gmail e Yahoo exigem para quem envia muito.
        $records[] = [
            'type' => 'TXT',
            'name' => "_dmarc.{$domain->domain}",
            'value' => 'v=DMARC1; p=none;',
            'purpose' => 'dmarc',
            'status' => 'recommended',
        ];

        return $records;
    }

    private function recordStatus(?string $sesStatus): string
    {
        return match ($sesStatus) {
            IdentityStatus::SUCCESS => 'ok',
            IdentityStatus::FAILED => 'failed',
            default => 'pending',
        };
    }
}
