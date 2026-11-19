<?php

namespace App\Modules\Mailing\Contracts;

use App\Models\Tenant;
use App\Modules\Mailing\Models\SmtpCredential;
use Illuminate\Support\Collection;

interface SmtpCredentialRepositoryContract
{
    /** @return Collection<int, SmtpCredential> mais recentes primeiro */
    public function forTenant(Tenant $tenant): Collection;

    public function countForTenant(Tenant $tenant): int;

    /** Fora do scope: o relay chega sem workspace e descobre qual é pela credencial. */
    public function findByUsername(string $username): ?SmtpCredential;

    public function markUsed(SmtpCredential $credential): void;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): SmtpCredential;

    public function delete(SmtpCredential $credential): void;
}
