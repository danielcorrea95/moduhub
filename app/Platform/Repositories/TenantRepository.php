<?php

namespace App\Platform\Repositories;

use App\Models\Tenant;
use App\Models\User;
use App\Models\TenantUser;
use App\Platform\Contracts\TenantRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TenantRepository implements TenantRepositoryContract
{
    public function workspacesForUser(User $user): Collection
    {
        return $user->tenants()
            ->orderBy('tenants.name')
            ->get()
            ->map(fn (Tenant $t) => [
                'id' => $t->getKey(),
                'name' => $t->name,
                'slug' => $t->slug,
                'role' => $t->pivot->role,
            ]);
    }

    public function activeModuleKeys(Tenant $tenant): array
    {
        return $tenant->activeModules()->pluck('key')->all();
    }

    public function paginateForAdmin(?string $search, int $perPage = 30): LengthAwarePaginator
    {
        $search = trim((string) $search);
        $digits = preg_replace('/\D/', '', $search);

        return Tenant::query()
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")
                ->when($digits !== '', fn (Builder $q) => $q->orWhereRaw(
                    "REPLACE(REPLACE(REPLACE(tax_id, '.', ''), '/', ''), '-', '') like ?",
                    ["%{$digits}%"],
                ))
                ->orWhereHas('users', fn (Builder $q) => $q->where('email', 'like', "%{$search}%"))))
            ->with($this->adminRelations())
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function loadForAdmin(Tenant $tenant): Tenant
    {
        return $tenant->load($this->adminRelations());
    }

    /** @return array<string, \Closure> */
    private function adminRelations(): array
    {
        return [
            'users' => fn ($q) => $q->wherePivot('role', TenantUser::ROLE_OWNER),
            // O admin age fora do workspace do cliente: sem tirar o scope, viriam só as assinaturas do
            // workspace da sessão dele. A atual é a de maior id.
            'subscriptions' => fn ($q) => $q->withoutTenantScope()->orderByDesc('id')->with('plans.modules'),
        ];
    }
}
