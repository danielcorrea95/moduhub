<?php

namespace App\Modules\Invoices\Repositories;

use App\Modules\Invoices\Contracts\CertificateEventRepositoryContract;
use App\Modules\Invoices\Models\CertificateEvent;
use App\Modules\Invoices\Models\Issuer;
use Illuminate\Support\Collection;

class CertificateEventRepository implements CertificateEventRepositoryContract
{
    public function record(array $attributes): CertificateEvent
    {
        return CertificateEvent::query()->create($attributes);
    }

    public function recentForIssuer(Issuer $issuer, int $limit): Collection
    {
        return CertificateEvent::query()
            ->where('issuer_id', $issuer->getKey())
            ->latest('created_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
