<?php

namespace App\Modules\Mailing\Services\Relay;

use App\Modules\Mailing\Contracts\EmailUsageRepositoryContract;
use App\Modules\Mailing\Contracts\SendingDomainRepositoryContract;
use App\Modules\Mailing\Contracts\SmtpCredentialRepositoryContract;
use App\Modules\Mailing\Exceptions\EmailQuotaExceeded;
use App\Modules\Mailing\Exceptions\RelayRejected;
use App\Modules\Mailing\Models\SmtpCredential;
use App\Modules\Mailing\Services\EmailDeliveryService;
use App\Modules\Mailing\Services\Quota\EmailQuotaService;
use App\Modules\Mailing\Services\Suppression\EmailSuppressionService;
use App\Tenancy\CurrentTenant;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * Regras do relay `relay.moduhub.com.br`. O servidor SMTP (smtp-relay/, Haraka) só fala o
 * protocolo: confere o login aqui e, no fim do DATA, entrega a mensagem bruta para `relay()`.
 */
class SmtpRelayService
{
    public function __construct(
        private SmtpCredentialRepositoryContract $credentials,
        private SendingDomainRepositoryContract $domains,
        private EmailUsageRepositoryContract $usage,
        private EmailQuotaService $quota,
        private EmailSuppressionService $suppressions,
        private EmailDeliveryService $deliveries,
        private RelayMailTransport $transport,
        private CurrentTenant $current,
        private RateLimiter $limiter,
    ) {}

    /** @throws RelayRejected */
    public function authenticate(string $username, string $password, ?string $remoteIp): SmtpCredential
    {
        $key = 'smtp-relay-auth:'.($remoteIp ?? 'unknown');

        if ($this->limiter->tooManyAttempts($key, config('mailing.relay.auth_attempts'))) {
            throw RelayRejected::tooManyAttempts($this->limiter->availableIn($key));
        }

        $credential = $this->credentials->findByUsername($username);

        if ($credential === null
            || ! hash_equals($credential->password_hash, hash('sha256', $password))
            || $credential->tenant?->status !== 'active') {
            $this->limiter->hit($key, config('mailing.relay.auth_decay_seconds'));

            throw RelayRejected::invalidCredentials();
        }

        return $credential;
    }

    /**
     * Confere e envia uma mensagem. Conta uma na cota, qualquer que seja o número de destinatários.
     *
     * @param  list<string>  $recipients  envelope (RCPT TO), que inclui as cópias ocultas
     * @return ?string id do SES
     *
     * @throws RelayRejected
     */
    public function relay(SmtpCredential $credential, string $raw, array $recipients): ?string
    {
        $tenant = $credential->tenant;

        return $this->current->run($tenant, function () use ($credential, $tenant, $raw, $recipients) {
            if (strlen($raw) > config('mailing.relay.max_bytes')) {
                throw RelayRejected::tooLarge(config('mailing.relay.max_bytes'));
            }

            if ($this->usage->subscriptionFor($tenant)?->isBlocked()) {
                throw RelayRejected::workspaceBlocked();
            }

            $domain = $this->domains->forTenant($tenant);

            if ($domain === null || ! $domain->isVerified()) {
                throw RelayRejected::domainNotVerified();
            }

            $message = RelayedMessage::parse($raw);

            foreach ($message->originatorDomains() as $originDomain) {
                if ($originDomain !== Str::lower($domain->domain)) {
                    throw RelayRejected::senderNotAllowed($domain->domain);
                }
            }

            $allowed = array_values(array_filter(
                array_unique(array_map(fn (string $email) => Str::lower(trim($email)), $recipients)),
                fn (string $email) => ! $this->suppressions->isSuppressed($tenant, $email),
            ));

            if ($allowed === []) {
                throw RelayRejected::allRecipientsSuppressed();
            }

            try {
                $this->quota->reserve($tenant);
            } catch (EmailQuotaExceeded $e) {
                throw RelayRejected::quotaExceeded($e);
            }

            try {
                $messageId = $this->transport->send($message, $allowed);
            } catch (RelayRejected $e) {
                $this->quota->release($tenant);

                throw $e;
            }

            // A mensagem já saiu: um erro daqui em diante viraria 4xx e o cliente mandaria de novo.
            try {
                if ($messageId !== null) {
                    $this->deliveries->recordRelayed($messageId, $allowed, $message->fromAddress(), $message->subject);
                }

                $this->credentials->markUsed($credential);
            } catch (Throwable $e) {
                report($e);
            }

            return $messageId;
        });
    }
}
