<?php

namespace App\Modules\Invoices\Repositories;

use App\Modules\Invoices\Contracts\DistributedDocumentRepositoryContract;
use App\Modules\Invoices\Models\DistributedDocument;
use Illuminate\Support\Collection;

class DistributedDocumentRepository implements DistributedDocumentRepositoryContract
{
    public function eventsFor(string $accessKey): Collection
    {
        return DistributedDocument::query()
            ->where('access_key', $accessKey)
            ->where('document_type', DistributedDocument::TYPE_EVENT)
            ->orderBy('nsu')
            ->get();
    }
}
