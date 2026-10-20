<?php

namespace App\Modules\Billing\Repositories;

use App\Modules\Billing\Contracts\BillingAccountRepositoryContract;
use App\Modules\Billing\Models\BillingAccount;

class BillingAccountRepository implements BillingAccountRepositoryContract
{
    public function current(): ?BillingAccount
    {
        return BillingAccount::query()->first();
    }

    public function findByGatewayCustomer(string $customerId): ?BillingAccount
    {
        // O webhook do Asaas chega sem workspace; o cliente é que diz de quem é a cobrança.
        return BillingAccount::query()
            ->withoutTenantScope()
            ->with('tenant')
            ->where('gateway_customer_id', $customerId)
            ->first();
    }
}
