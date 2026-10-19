<?php

namespace App\Modules\Invoices\Repositories;

use App\Modules\Invoices\Contracts\InvoiceRepositoryContract;
use App\Modules\Invoices\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InvoiceRepository implements InvoiceRepositoryContract
{
    public function paginateForListing(?string $status = null, ?string $search = null, int $perPage = 20, ?string $externalId = null): LengthAwarePaginator
    {
        return Invoice::query()
            ->when($status !== null, fn (Builder $q) => $q->where('status', $status))
            ->when($externalId !== null, fn (Builder $q) => $q->where('external_id', $externalId))
            ->when($search !== null, function (Builder $q) use ($search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $digits = preg_replace('/\D/', '', $search);

                $q->where(function (Builder $w) use ($term, $digits) {
                    $w->where('taker_name', 'like', $term)->orWhere('nfse_number', 'like', $term);
                    if ($digits !== '') {
                        $w->orWhere('taker_document', 'like', '%'.$digits.'%')->orWhere('access_key', 'like', '%'.$digits.'%');
                    }
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function countByStatus(): Collection
    {
        return Invoice::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total);
    }

    public function findReusableByExternalId(string $externalId): ?Invoice
    {
        return Invoice::query()
            ->where('external_id', $externalId)
            ->where('status', '!=', Invoice::STATUS_REJECTED)
            ->latest()
            ->first();
    }

    public function findRejected(string $id): ?Invoice
    {
        return Invoice::query()
            ->whereKey($id)
            ->where('status', Invoice::STATUS_REJECTED)
            ->first();
    }

    public function findPendingReplacement(Invoice $original): ?Invoice
    {
        return $original->replacements()->where('status', Invoice::STATUS_PENDING)->latest()->first();
    }

    public function findIssued(string $id): ?Invoice
    {
        return Invoice::query()
            ->whereKey($id)
            ->where('status', Invoice::STATUS_ISSUED)
            ->first();
    }

    public function findByAccessKeyForTenant(string $tenantId, string $accessKey): ?Invoice
    {
        return Invoice::query()
            ->withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('access_key', $accessKey)
            ->first();
    }

    public function countBilledBetween(string $tenantId, \DateTimeInterface $start, \DateTimeInterface $end): int
    {
        return Invoice::query()
            ->withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', Invoice::STATUS_REJECTED)
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }
}
