<?php

namespace App\Modules\Billing\Contracts;

use App\Modules\Billing\Models\BillingAccount;

interface BillingAccountRepositoryContract
{
    /** Cadastro de cobrança do workspace atual. */
    public function current(): ?BillingAccount;

    /** Busca fora do tenant: o webhook só sabe o cliente do Asaas. */
    public function findByGatewayCustomer(string $customerId): ?BillingAccount;
}
