<?php

namespace App\Modules\Mailing\Contracts;

/**
 * Lista de supressão da conta SES. Ela vale para a conta inteira: um endereço que devolveu para
 * um workspace fica bloqueado para todos até sair daqui.
 */
interface SuppressionListGatewayContract
{
    public function remove(string $email): void;
}
