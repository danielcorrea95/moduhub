<?php

namespace App\Platform\Contracts;

use App\Models\WebhookEndpoint;
use Illuminate\Support\Collection;

interface WebhookRepositoryContract
{
    public function listForCurrentTenant(): Collection;

    public function recentDeliveries(WebhookEndpoint $endpoint, int $limit = 50): Collection;
}
