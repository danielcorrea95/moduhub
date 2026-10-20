<?php

namespace App\Modules\Billing\Exceptions;

use RuntimeException;

/**
 * O Asaas recusou a operação (cartão negado, CPF inválido) ou não respondeu. A mensagem do Asaas
 * já vem em português e pode ir para a tela.
 */
class PaymentGatewayException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $gatewayCode = null)
    {
        parent::__construct($message);
    }
}
