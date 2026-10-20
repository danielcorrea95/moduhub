<?php

namespace App\Modules\Signatures\Repositories;

use App\Modules\Signatures\Contracts\DocumentRepositoryContract;
use App\Modules\Signatures\Models\Document;
use App\Tenancy\TenantScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DocumentRepository implements DocumentRepositoryContract
{
    public function paginateForListing(?string $statusFilter = null, ?string $search = null, int $perPage = 20, ?string $externalId = null): LengthAwarePaginator
    {
        return Document::query()
            ->with([
                'creator:id,name,email',
                'signers:id,document_id,name,email,status,order,signed_at',
            ])
            ->withCount(['signers', 'fields'])
            ->when(
                $statusFilter !== null,
                fn (Builder $q) => $q->whereIn('status', Document::LISTING_FILTERS[$statusFilter]),
            )
            ->when($externalId !== null, fn (Builder $q) => $q->where('external_id', $externalId))
            ->when($search !== null, function (Builder $q) use ($search) {
                $term = '%'.addcslashes($search, '%_\\').'%';

                $q->where(fn (Builder $w) => $w
                    ->where('name', 'like', $term)
                    ->orWhereHas('signers', fn (Builder $s) => $s
                        ->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)));
            })
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function countByStatus(): Collection
    {
        return Document::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total);
    }

    public function latestInProgress(int $limit = 5): Collection
    {
        return Document::query()
            ->with('signers:id,document_id,name,status,order')
            ->whereIn('status', Document::LISTING_FILTERS['in_progress'])
            ->orderByDesc('sent_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function findWithRelations(string $id): ?Document
    {
        return Document::query()
            ->with([
                'creator:id,name,email',
                'signers',
                'fields',
                'events' => fn ($q) => $q->with('user:id,name,email')->limit(50),
            ])
            ->find($id);
    }

    public function findByShortId(string $shortId): ?Document
    {
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $shortId) ?? '');
        if (strlen($normalized) !== 12) {
            return null;
        }

        // Página pública: o visitante pode estar anônimo ou logado em outro workspace, então nenhuma das
        // relações passa pelo scope. O documento já foi escolhido pelo id; elas só seguem a chave dele.
        return Document::query()
            ->withoutGlobalScope(TenantScope::class)
            ->with([
                'tenant',
                'signers' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->orderBy('order'),
                'events' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->orderByDesc('created_at')->limit(100),
            ])
            ->whereRaw('UPPER(RIGHT(id, 12)) = ?', [$normalized])
            ->first();
    }

    public function countSentBetween(string $tenantId, \DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return Document::query()
            ->withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', $from)
            ->where('sent_at', '<', $to)
            ->count();
    }

    public function countCompletedBetween(string $tenantId, \DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return Document::query()
            ->withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('status', Document::STATUS_COMPLETED)
            ->where('completed_at', '>=', $from)
            ->where('completed_at', '<', $to)
            ->count();
    }
}
