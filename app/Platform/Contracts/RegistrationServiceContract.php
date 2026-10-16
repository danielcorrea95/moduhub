<?php

namespace App\Platform\Contracts;

use App\Models\User;

interface RegistrationServiceContract
{
    /**
     * Register user and attach to initial workspace (via invitation when valid, otherwise create one).
     *
     * @return array{user: User, tenant_id: string}
     */
    public function register(array $attrs): array;
}
