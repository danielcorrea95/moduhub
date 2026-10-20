<?php

namespace App\Modules\Mailing\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Recusa domínios que o cliente não controla: provedores de caixa postal (o DMARC deles reprova
 * envio por terceiros) e o próprio domínio da Moduhub.
 */
class OwnSendingDomain implements ValidationRule
{
    public const MAILBOX_PROVIDERS = [
        'gmail.com', 'googlemail.com', 'hotmail.com', 'hotmail.com.br', 'outlook.com', 'outlook.com.br',
        'live.com', 'msn.com', 'yahoo.com', 'yahoo.com.br', 'ymail.com', 'icloud.com', 'me.com', 'mac.com',
        'aol.com', 'proton.me', 'protonmail.com', 'zoho.com', 'gmx.com', 'uol.com.br', 'bol.com.br',
        'terra.com.br', 'ig.com.br', 'r7.com', 'globo.com', 'globomail.com', 'oi.com.br',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domain = (string) $value;

        if (in_array($domain, self::MAILBOX_PROVIDERS, true)) {
            $fail('Use um domínio da sua empresa. Gmail, Outlook e outros provedores de e-mail não permitem envio em seu nome.');

            return;
        }

        $platform = Str::lower(Str::after((string) config('mail.from.address'), '@'));

        if ($platform !== '' && ($domain === $platform || Str::endsWith($domain, '.'.$platform))) {
            $fail('Esse é o domínio da Moduhub. Informe o domínio da sua empresa.');
        }
    }
}
