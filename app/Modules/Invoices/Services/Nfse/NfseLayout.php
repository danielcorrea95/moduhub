<?php

namespace App\Modules\Invoices\Services\Nfse;

use App\Modules\Invoices\Models\Issuer;

/**
 * Leiaute (pasta de schemas) que a Sefin aplica no ambiente do emitente.
 * Os dois usam versao="1.01" no XML; o que muda é a estrutura definida pela NT 009.
 */
final class NfseLayout
{
    public const V1_01 = '1.01';

    public const V1_01_NT009 = '1.01-nt009';

    public static function for(Issuer $issuer): string
    {
        return (string) config('invoices.layouts.'.$issuer->environment, self::V1_01);
    }

    public static function isNt009(Issuer $issuer): bool
    {
        return self::for($issuer) === self::V1_01_NT009;
    }
}
