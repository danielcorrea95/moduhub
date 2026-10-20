<?php

namespace App\Modules\Mailing\Repositories;

use App\Modules\Mailing\Contracts\EmailSuppressionRepositoryContract;
use App\Modules\Mailing\Models\EmailSuppression;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EmailSuppressionRepository implements EmailSuppressionRepositoryContract
{
    public function put(string $tenantId, string $email, array $attributes): EmailSuppression
    {
        return EmailSuppression::query()->withoutTenantScope()->updateOrCreate(
            ['tenant_id' => $tenantId, 'email' => $email],
            $attributes,
        );
    }

    public function find(string $tenantId, string $email): ?EmailSuppression
    {
        return EmailSuppression::query()
            ->withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('email', $email)
            ->first();
    }

    public function paginateForListing(?string $search, int $perPage = 25): LengthAwarePaginator
    {
        return EmailSuppression::query()
            ->when($search !== null, fn ($q) => $q->where('email', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->latest('updated_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function count(): int
    {
        return EmailSuppression::query()->count();
    }

    public function delete(EmailSuppression $suppression): void
    {
        $suppression->delete();
    }
}
