<?php

namespace App\Modules\Mailing\Exceptions;

use RuntimeException;
use Throwable;

/**
 * O SES recusou o e-mail de teste. A mensagem já vem pronta para mostrar ao cliente.
 */
class TestEmailFailed extends RuntimeException
{
    public static function from(Throwable $e): self
    {
        $reason = $e->getMessage();

        $message = match (true) {
            str_contains($reason, 'not verified') => 'O envio de e-mails ainda está em modo de teste na Moduhub e só entrega para endereços liberados. Fale com o suporte.',
            str_contains($reason, 'suppression') => 'Seu endereço está bloqueado por devoluções ou reclamações anteriores. Fale com o suporte.',
            default => 'Não foi possível enviar o e-mail de teste agora. Tente de novo em alguns minutos.',
        };

        return new self($message, 0, $e);
    }
}
