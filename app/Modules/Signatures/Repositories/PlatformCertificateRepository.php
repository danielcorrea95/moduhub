<?php

namespace App\Modules\Signatures\Repositories;

use App\Modules\Signatures\Contracts\PlatformCertificateRepositoryContract;
use App\Modules\Signatures\Models\PlatformCertificate;
use Illuminate\Support\Collection;

class PlatformCertificateRepository implements PlatformCertificateRepositoryContract
{
    public function current(): ?PlatformCertificate
    {
        return PlatformCertificate::query()
            ->whereNull('retired_at')
            ->latest()
            ->first();
    }

    public function history(int $limit): Collection
    {
        return PlatformCertificate::query()
            ->latest()
            ->limit($limit)
            ->get();
    }
}
