<?php

namespace App\Modules\Invoices\Services\Nfse;

use Illuminate\Support\Str;

/**
 * Tabela de municípios do IBGE (Anexo V do manual da Sefin Nacional), gerada a partir da API de localidades do IBGE
 * e guardada em resources/nfse/municipios-ibge.json no formato {"código": ["nome", "UF"]}.
 */
class Municipalities
{
    /** @var array<string, array{0: string, 1: string}>|null */
    private static ?array $table = null;

    public static function exists(?string $code): bool
    {
        return $code !== null && isset(self::table()[$code]);
    }

    public static function name(?string $code): ?string
    {
        return $code === null ? null : (self::table()[$code][0] ?? null);
    }

    public static function uf(?string $code): ?string
    {
        return $code === null ? null : (self::table()[$code][1] ?? null);
    }

    /** "São Paulo / SP", ou null para código desconhecido. */
    public static function label(?string $code): ?string
    {
        $entry = $code === null ? null : (self::table()[$code] ?? null);

        return $entry === null ? null : $entry[0].' / '.$entry[1];
    }

    /**
     * Busca por nome, sem acento nem caixa: nome igual, depois os que começam pelo termo, depois os que o contêm. Aceita "nome/UF" para filtrar o estado.
     *
     * @return list<array{code: string, name: string, uf: string}>
     */
    public static function search(string $term, int $limit = 10): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        if (ctype_digit($term)) {
            return self::exists($term) ? [self::row($term)] : [];
        }

        $uf = null;
        if (preg_match('#^(.+?)\s*[/,-]\s*([A-Za-z]{2})$#', $term, $m)) {
            [$term, $uf] = [$m[1], strtoupper($m[2])];
        }

        $needle = self::normalize($term);
        $exact = [];
        $prefix = [];
        $contains = [];

        foreach (self::table() as $code => [$name, $state]) {
            if ($uf !== null && $state !== $uf) {
                continue;
            }

            $normalized = self::normalize($name);
            $position = strpos($normalized, $needle);
            if ($normalized === $needle) {
                $exact[] = (string) $code;
            } elseif ($position === 0) {
                $prefix[(string) $code] = strlen($normalized);
            } elseif ($position !== false) {
                $contains[] = (string) $code;
            }
        }

        // "sao paulo" precisa achar São Paulo antes de São Paulo de Olivença: o nome mais curto vem primeiro.
        asort($prefix);

        return array_map(self::row(...), array_slice([...$exact, ...array_map('strval', array_keys($prefix)), ...$contains], 0, $limit));
    }

    /**
     * Códigos IBGE, opcionalmente só dos estados pedidos.
     *
     * @param  list<string>  $ufs
     * @return list<string>
     */
    public static function codes(array $ufs = []): array
    {
        $ufs = array_map('strtoupper', $ufs);
        $codes = [];
        foreach (self::table() as $code => [, $uf]) {
            if ($ufs === [] || in_array($uf, $ufs, true)) {
                $codes[] = (string) $code;
            }
        }

        return $codes;
    }

    /**
     * Códigos cujo nome bate com o termo (sem acento nem caixa), para filtrar listas guardadas no banco.
     *
     * @return list<string>
     */
    public static function codesMatching(string $term): array
    {
        $needle = self::normalize(trim($term));
        if ($needle === '') {
            return [];
        }

        $codes = [];
        foreach (self::table() as $code => [$name]) {
            if (str_contains(self::normalize($name), $needle)) {
                $codes[] = (string) $code;
            }
        }

        return $codes;
    }

    /** @return list<string> siglas das UFs, em ordem */
    public static function states(): array
    {
        $ufs = array_values(array_unique(array_column(self::table(), 1)));
        sort($ufs);

        return $ufs;
    }

    /** @return array{code: string, name: string, uf: string} */
    private static function row(string $code): array
    {
        [$name, $uf] = self::table()[$code];

        return ['code' => $code, 'name' => $name, 'uf' => $uf];
    }

    private static function normalize(string $value): string
    {
        return Str::lower(Str::ascii($value));
    }

    /** @return array<string, array{0: string, 1: string}> */
    private static function table(): array
    {
        return self::$table ??= json_decode(
            (string) file_get_contents(resource_path('nfse/municipios-ibge.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
