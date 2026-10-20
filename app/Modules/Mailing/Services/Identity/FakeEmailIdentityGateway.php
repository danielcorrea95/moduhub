<?php

namespace App\Modules\Mailing\Services\Identity;

use App\Modules\Mailing\Contracts\EmailIdentityGatewayContract;

/**
 * Para desenvolvimento e testes, sem AWS. A identidade nasce pendente e passa a verificada na
 * primeira consulta, como se o cliente tivesse publicado o DNS. `holdPending()` e `fail()` simulam
 * DNS ausente ou removido (o estado vive só no processo).
 */
class FakeEmailIdentityGateway implements EmailIdentityGatewayContract
{
    /** @var array<string, bool> */
    public array $identities = [];

    /** @var array<string, string> */
    private array $forced = [];

    public function createDomainIdentity(string $domain, string $mailFromDomain): IdentityStatus
    {
        $this->identities[$domain] = true;

        return new IdentityStatus(false, $this->tokens($domain), IdentityStatus::PENDING, IdentityStatus::PENDING);
    }

    public function status(string $domain): IdentityStatus
    {
        $state = $this->forced[$domain] ?? IdentityStatus::SUCCESS;

        return new IdentityStatus($state === IdentityStatus::SUCCESS, $this->tokens($domain), $state, $state);
    }

    public function deleteDomainIdentity(string $domain): void
    {
        unset($this->identities[$domain], $this->forced[$domain]);
    }

    public function holdPending(string $domain): void
    {
        $this->forced[$domain] = IdentityStatus::PENDING;
    }

    public function fail(string $domain): void
    {
        $this->forced[$domain] = IdentityStatus::FAILED;
    }

    /** @return list<string> */
    private function tokens(string $domain): array
    {
        return array_map(fn (int $i) => substr(hash('sha256', $domain.$i), 0, 32), [1, 2, 3]);
    }
}
