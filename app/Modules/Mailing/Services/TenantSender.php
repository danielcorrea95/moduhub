<?php

namespace App\Modules\Mailing\Services;

use App\Models\Tenant;
use App\Modules\Mailing\Contracts\SendingDomainRepositoryContract;
use Illuminate\Mail\Mailables\Address;

/**
 * Remetente dos e-mails que o workspace manda para fora (signatários, tomadores). Só com domínio
 * verificado; sem ele, o Mailable fica com o remetente padrão da Moduhub. E-mails da conta
 * (senha, verificação de cadastro) não passam por aqui.
 */
class TenantSender
{
    public function __construct(
        private SendingDomainRepositoryContract $domains,
    ) {}

    public function from(?Tenant $tenant): ?Address
    {
        $domain = $tenant ? $this->domains->forTenant($tenant) : null;

        return $domain?->isVerified() ? new Address($domain->fromAddress(), $domain->from_name) : null;
    }

    /** @return list<Address> */
    public function replyTo(?Tenant $tenant): array
    {
        $domain = $tenant ? $this->domains->forTenant($tenant) : null;

        return $domain?->isVerified() && $domain->reply_to ? [new Address($domain->reply_to, $domain->from_name)] : [];
    }
}
