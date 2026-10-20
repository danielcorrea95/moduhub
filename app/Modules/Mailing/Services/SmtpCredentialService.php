<?php

namespace App\Modules\Mailing\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Mailing\Contracts\SmtpCredentialRepositoryContract;
use App\Modules\Mailing\Models\SmtpCredential;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Str;

class SmtpCredentialService
{
    public function __construct(
        private SmtpCredentialRepositoryContract $credentials,
        private CurrentTenant $current,
    ) {}

    /**
     * @return array{credential: SmtpCredential, password: string} a senha em claro só existe aqui
     */
    public function issue(Tenant $tenant, User $creator, string $name): array
    {
        $password = Str::random(40);

        $credential = $this->current->run($tenant, fn () => $this->credentials->create([
            'tenant_id' => $tenant->getKey(),
            'name' => $name,
            'username' => SmtpCredential::USERNAME_PREFIX.Str::lower(Str::random(12)),
            'password_hash' => hash('sha256', $password),
            'last_four' => substr($password, -4),
            'created_by' => $creator->getKey(),
        ]));

        return ['credential' => $credential, 'password' => $password];
    }

    public function revoke(SmtpCredential $credential): void
    {
        $this->credentials->delete($credential);
    }
}
