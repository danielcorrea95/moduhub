<?php

namespace App\Modules\Invoices\Policies;

use App\Models\TenantUser;
use App\Models\User;
use App\Modules\Invoices\Models\Invoice;
use App\Tenancy\CurrentTenant;

class InvoicePolicy
{
    public function __construct(protected CurrentTenant $currentTenant) {}

    public function viewAny(User $user): bool
    {
        return $this->role($user) !== null;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->role($user) !== null && $invoice->tenant_id === $this->currentTenant->get()?->getKey();
    }

    /** Emitir é rotina de faturamento: qualquer membro do workspace pode. */
    public function issue(User $user): bool
    {
        return $this->role($user) !== null;
    }

    public function sync(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }

    /** Cancelamento desfaz um documento fiscal já transmitido: só quem administra o workspace. */
    public function cancel(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice)
            && in_array($this->role($user), [TenantUser::ROLE_OWNER, TenantUser::ROLE_ADMIN], true);
    }

    /** Substituir também cancela a nota original na Sefin: mesma alçada do cancelamento. */
    public function replace(User $user, Invoice $invoice): bool
    {
        return $this->cancel($user, $invoice);
    }

    protected function role(User $user): ?string
    {
        $tenant = $this->currentTenant->get();

        return $tenant !== null && $user->belongsToTenant($tenant) ? $user->tenantRole($tenant) : null;
    }
}
