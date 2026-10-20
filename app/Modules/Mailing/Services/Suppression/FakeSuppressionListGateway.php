<?php

namespace App\Modules\Mailing\Services\Suppression;

use App\Modules\Mailing\Contracts\SuppressionListGatewayContract;

/** Desenvolvimento e testes: guarda os endereços removidos em vez de chamar a AWS. */
class FakeSuppressionListGateway implements SuppressionListGatewayContract
{
    /** @var list<string> */
    public array $removed = [];

    public function remove(string $email): void
    {
        $this->removed[] = $email;
    }
}
