<?php

namespace App\Modules\Mailing\Exceptions;

use RuntimeException;

/**
 * O relay recusou a mensagem. O servidor SMTP repassa o código e o texto ao sistema do cliente:
 * 5xx o cliente não deve tentar de novo, 4xx pode tentar mais tarde.
 */
class RelayRejected extends RuntimeException
{
    public function __construct(
        public readonly int $smtpCode,
        public readonly string $enhancedCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function invalidCredentials(): self
    {
        return new self(535, '5.7.8', 'Usuário ou senha SMTP inválidos.');
    }

    public static function tooManyAttempts(int $seconds): self
    {
        return new self(454, '4.7.0', "Muitas tentativas de login. Tente de novo em {$seconds} segundos.");
    }

    public static function workspaceBlocked(): self
    {
        return new self(550, '5.7.1', 'Workspace com pagamento pendente: o envio está bloqueado até a regularização.');
    }

    public static function domainNotVerified(): self
    {
        return new self(550, '5.7.1', 'O workspace não tem domínio de envio verificado na Moduhub.');
    }

    public static function senderNotAllowed(string $domain): self
    {
        return new self(550, '5.7.1', "O remetente precisa ser um endereço do domínio verificado ({$domain}).");
    }

    public static function malformed(string $reason): self
    {
        return new self(550, '5.6.0', $reason);
    }

    public static function tooLarge(int $maxBytes): self
    {
        $mb = intdiv($maxBytes, 1024 * 1024);

        return new self(552, '5.3.4', "Mensagem maior que o limite de {$mb} MB.");
    }

    public static function allRecipientsSuppressed(): self
    {
        return new self(550, '5.1.1', 'Todos os destinatários estão na lista de bloqueio do workspace (devolução ou spam).');
    }

    public static function quotaExceeded(EmailQuotaExceeded $e): self
    {
        return new self(550, '5.4.5', $e->getMessage());
    }

    public static function rejectedBySes(string $reason): self
    {
        return new self(554, '5.7.1', "Mensagem recusada pelo provedor de envio: {$reason}");
    }

    public static function temporaryFailure(): self
    {
        return new self(451, '4.3.0', 'Falha temporária ao enviar. Tente de novo em alguns minutos.');
    }

    public function isPermanent(): bool
    {
        return $this->smtpCode >= 500;
    }
}
