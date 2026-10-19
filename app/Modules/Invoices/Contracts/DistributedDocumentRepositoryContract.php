<?php

namespace App\Modules\Invoices\Contracts;

use App\Modules\Invoices\Models\DistributedDocument;
use Illuminate\Support\Collection;

interface DistributedDocumentRepositoryContract
{
    /** @return Collection<int, DistributedDocument> eventos de uma NFS-e, na ordem em que o ADN distribuiu */
    public function eventsFor(string $accessKey): Collection;
}
