<?php

namespace App\Modules\Invoices\Rules;

/**
 * Regras dos códigos do IBS/CBS, iguais no emitente (padrão) e na nota (sobrescrita). Os três vêm juntos:
 * sem qualquer um deles a DPS não leva o grupo IBSCBS.
 */
final class IbsCbsCodes
{
    /** @return array<string, mixed> */
    public static function rules(string $cst, string $class, string $operation, mixed $cstValue): array
    {
        $cstValue = is_string($cstValue) ? preg_replace('/\D/', '', $cstValue) : '';

        return [
            $cst => "nullable|required_with:{$class},{$operation}|digits:3",
            // O cClassTrib começa pelo CST a que pertence (ex.: CST 000, cClassTrib 000001).
            $class => array_filter([
                "required_with:{$cst},{$operation}",
                'nullable',
                'digits:6',
                $cstValue !== '' ? 'starts_with:'.$cstValue : null,
            ]),
            $operation => "nullable|required_with:{$cst},{$class}|digits:6",
        ];
    }

    /** @return array<string, string> */
    public static function messages(string $cst, string $class, string $operation): array
    {
        return [
            "{$cst}.required_with" => 'Informe o CST do IBS/CBS junto com a classificação e o indicador de operação.',
            "{$class}.required_with" => 'Informe a classificação tributária (cClassTrib) junto com o CST.',
            "{$class}.starts_with" => 'A classificação tributária precisa começar pelo CST informado.',
            "{$operation}.required_with" => 'Informe o código indicador de operação (cIndOp) junto com o CST.',
        ];
    }
}
