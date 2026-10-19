<?php

namespace App\Modules\Invoices\Repositories;

use App\Modules\Invoices\Contracts\ReceivedInvoiceRepositoryContract;
use App\Modules\Invoices\Models\ReceivedInvoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReceivedInvoiceRepository implements ReceivedInvoiceRepositoryContract
{
    public function paginateForListing(?string $role, ?string $status, ?string $search, int $perPage = 20): LengthAwarePaginator
    {
        return ReceivedInvoice::query()
            ->when($role !== null, fn (Builder $q) => $q->where('role', $role))
            ->when($status !== null, fn (Builder $q) => $q->where('status', $status))
            ->when($search !== null, function (Builder $q) use ($search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $digits = preg_replace('/\D/', '', $search);

                $q->where(function (Builder $w) use ($term, $digits) {
                    $w->where('provider_name', 'like', $term)->orWhere('taker_name', 'like', $term)->orWhere('nfse_number', 'like', $term);
                    if ($digits !== '') {
                        $w->orWhere('provider_document', 'like', '%'.$digits.'%')->orWhere('access_key', 'like', '%'.$digits.'%');
                    }
                });
            })
            ->orderByDesc('issued_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function countByRole(): Collection
    {
        return ReceivedInvoice::query()
            ->selectRaw('role, COUNT(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role')
            ->map(fn ($total) => (int) $total);
    }

    public function findForTenant(string $tenantId, string $environment, string $accessKey): ?ReceivedInvoice
    {
        return ReceivedInvoice::query()
            ->withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('environment', $environment)
            ->where('access_key', $accessKey)
            ->first();
    }
}
