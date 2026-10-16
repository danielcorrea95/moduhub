<?php

namespace App\Modules\Invoices\Services\Nfse;

/**
 * Os campos texto do leiaute nacional (TSString) só aceitam Latin-1 sem quebras de linha
 * e sem espaço nas pontas. Texto vindo de CRM costuma trazer aspas tipográficas, travessões e emojis.
 */
final class NfseText
{
    private const REPLACEMENTS = [
        "\u{2018}" => "'", "\u{2019}" => "'", "\u{201C}" => '"', "\u{201D}" => '"',
        "\u{2013}" => '-', "\u{2014}" => '-', "\u{2026}" => '...', "\u{00A0}" => ' ',
        "\u{20AC}" => 'EUR', "\u{2022}" => '-',
    ];

    public static function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strtr($value, self::REPLACEMENTS);
        // Tudo acima de U+00FF não cabe no leiaute; quebras de linha e tabs viram espaço.
        $value = preg_replace('/[^\x{20}-\x{7E}\x{A0}-\x{FF}\s]/u', '', $value) ?? '';
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value === '' ? null : $value;
    }

    public static function money(string|float|int $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
