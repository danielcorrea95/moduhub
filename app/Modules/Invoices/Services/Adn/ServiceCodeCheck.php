<?php

namespace App\Modules\Invoices\Services\Adn;

/**
 * Resultado da consulta de um código de serviço nos parâmetros do município (ADN).
 *
 * `unknown` cobre tudo que não é resposta definitiva (ADN fora do ar, incidência em outro município):
 * nesses casos a emissão segue e quem decide é a Sefin.
 */
final readonly class ServiceCodeCheck
{
    public const PARAMETRIZED = 'parametrized';

    public const NOT_PARAMETRIZED = 'not_parametrized';

    public const UNKNOWN = 'unknown';

    private function __construct(
        public string $status,
        /** Código consultado no formato do ADN, ex.: 08.02.01.000 */
        public string $serviceCode,
        public ?float $rate = null,
    ) {}

    public static function parametrized(string $serviceCode, ?float $rate): self
    {
        return new self(self::PARAMETRIZED, $serviceCode, $rate);
    }

    public static function notParametrized(string $serviceCode): self
    {
        return new self(self::NOT_PARAMETRIZED, $serviceCode);
    }

    public static function unknown(string $serviceCode): self
    {
        return new self(self::UNKNOWN, $serviceCode);
    }

    public function isNotParametrized(): bool
    {
        return $this->status === self::NOT_PARAMETRIZED;
    }

    /** cTribNac (6 dígitos) + cTribMun (3 dígitos, 000 quando o município não desdobra), com pontos. */
    public static function formatCode(string $nationalCode, ?string $municipalCode): string
    {
        return preg_replace('/^(\d{2})(\d{2})(\d{2})$/', '$1.$2.$3', $nationalCode).'.'.str_pad($municipalCode ?? '', 3, '0', STR_PAD_LEFT);
    }
}
