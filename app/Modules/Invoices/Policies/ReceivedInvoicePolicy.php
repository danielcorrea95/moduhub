<?php

namespace App\Modules\Invoices\Policies;

use App\Models\TenantUser;
use App\Models\User;
use App\Modules\Invoices\Models\ReceivedInvoice;
use App\Tenancy\CurrentTenant;

class ReceivedInvoicePolicy
{
    public function __construct(protected CurrentTenant $currentTenant) {}

    public function viewAny(User $user): bool
    {
        return $this->role($user) !== null;
    }

    public function view(User $user, ReceivedInvoice $invoice): bool
    {
        return $this->role($user) !== null && $invoice->tenant_id === $this->currentTenant->get()?->getKey();
    }

    /** Confirmar ou rejeitar uma nota de terceiro é ato fiscal da empresa: só quem administra. */
    public function manifest(User $user, ReceivedInvoice $invoice): bool
    {
        return $this->view($user, $invoice) && $this->manifestAny($user);
    }

    public function manifestAny(User $user): bool
    {
        return in_array($this->role($user), [TenantUser::ROLE_OWNER, TenantUser::ROLE_ADMIN], true);
    }

    protected function role(User $user): ?string
    {
        $tenant = $this->currentTenant->get();

        return $tenant !== null && $user->belongsToTenant($tenant) ? $user->tenantRole($tenant) : null;
    }
}
