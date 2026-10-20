<?php

namespace App\Modules\Mailing\Enums;

/**
 * Provedor da caixa do destinatário, pelo domínio do endereço. Domínio próprio hospedado no
 * Google Workspace ou no Microsoft 365 cai em "outros": saber exigiria consultar o MX no envio.
 */
enum MailboxProvider: string
{
    case Google = 'google';
    case Outlook = 'outlook';
    case Yahoo = 'yahoo';
    case Icloud = 'icloud';
    case Uol = 'uol';
    case Terra = 'terra';
    case Other = 'other';

    private const DOMAINS = [
        'gmail.com' => self::Google,
        'googlemail.com' => self::Google,
        'outlook.com' => self::Outlook,
        'outlook.com.br' => self::Outlook,
        'hotmail.com' => self::Outlook,
        'hotmail.com.br' => self::Outlook,
        'live.com' => self::Outlook,
        'msn.com' => self::Outlook,
        'yahoo.com' => self::Yahoo,
        'yahoo.com.br' => self::Yahoo,
        'ymail.com' => self::Yahoo,
        'icloud.com' => self::Icloud,
        'me.com' => self::Icloud,
        'mac.com' => self::Icloud,
        'uol.com.br' => self::Uol,
        'bol.com.br' => self::Uol,
        'terra.com.br' => self::Terra,
    ];

    public static function fromEmail(string $email): self
    {
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));

        return self::DOMAINS[$domain] ?? self::Other;
    }

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Gmail',
            self::Outlook => 'Outlook / Hotmail',
            self::Yahoo => 'Yahoo',
            self::Icloud => 'iCloud',
            self::Uol => 'UOL / BOL',
            self::Terra => 'Terra',
            self::Other => 'Outros provedores',
        };
    }
}
