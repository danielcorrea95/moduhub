<?php

namespace App\Modules\Invoices\Contracts;

use App\Modules\Invoices\Models\CertificateEvent;
use App\Modules\Invoices\Models\Issuer;
use Illuminate\Support\Collection;

interface CertificateEventRepositoryContract
{
    /** @param  array<string, mixed>  $attributes */
    public function record(array $attributes): CertificateEvent;

    /** @return Collection<int, CertificateEvent> mais recentes primeiro */
    public function recentForIssuer(Issuer $issuer, int $limit): Collection;
}
