<?php

namespace App\Modules\Mailing\Exceptions;

use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * O envio passaria do limite do dia ou do mês. O relay SMTP e a API recusam a mensagem com este
 * texto; nada é contado.
 */
class EmailQuotaExceeded extends RuntimeException
{
    public const DAILY = 'daily';

    public const MONTHLY = 'monthly';

    public function __construct(
        string $message,
        public readonly string $window,
        public readonly int $limit,
        public readonly CarbonImmutable $resetsAt,
    ) {
        parent::__construct($message);
    }

    public static function daily(int $limit, CarbonImmutable $resetsAt): self
    {
        return new self(
            "Limite diário de {$limit} e-mails atingido. O envio volta à meia-noite (horário de Brasília).",
            self::DAILY,
            $limit,
            $resetsAt,
        );
    }

    public static function monthly(int $limit, CarbonImmutable $resetsAt): self
    {
        return new self(
            "Limite de {$limit} e-mails do mês atingido. O envio volta em {$resetsAt->format('d/m/Y')}. Mude de plano para enviar mais.",
            self::MONTHLY,
            $limit,
            $resetsAt,
        );
    }
}
