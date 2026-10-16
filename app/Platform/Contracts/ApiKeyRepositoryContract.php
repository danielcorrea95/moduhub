<?php

namespace App\Platform\Contracts;

use Illuminate\Support\Collection;

interface ApiKeyRepositoryContract
{
    public function listForCurrentTenant(): Collection;
}
