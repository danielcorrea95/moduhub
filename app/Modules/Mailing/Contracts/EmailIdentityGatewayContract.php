<?php

namespace App\Modules\Mailing\Contracts;

use App\Modules\Mailing\Services\Identity\IdentityStatus;

/**
 * Identidades de domínio no provedor de envio (SES). Só o provedor sabe se o DNS do cliente
 * já está certo.
 */
interface EmailIdentityGatewayContract
{
    /**
     * Cria a identidade com DKIM e o MAIL FROM próprio. Se ela já existir, devolve a atual.
     */
    public function createDomainIdentity(string $domain, string $mailFromDomain): IdentityStatus;

    public function status(string $domain): IdentityStatus;

    /** Remover uma identidade que não existe não é erro. */
    public function deleteDomainIdentity(string $domain): void;
}
