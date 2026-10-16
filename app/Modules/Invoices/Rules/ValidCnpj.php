<?php

namespace App\Modules\Invoices\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida os dígitos verificadores do CNPJ, inclusive no formato alfanumérico adotado pela
 * Receita Federal em 2026: as 12 primeiras posições aceitam letras, cujo valor no cálculo é
 * o código ASCII menos 48, e as duas últimas continuam numéricas.
 */
class ValidCnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cnpj = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $value) ?? '');

        if (! preg_match('/^[A-Z0-9]{12}\d{2}$/', $cnpj) || preg_match('/^(.)\1{13}$/', $cnpj)) {
            $fail('Informe um CNPJ válido.');

            return;
        }

        if (substr($cnpj, 12, 2) !== $this->checkDigits(substr($cnpj, 0, 12))) {
            $fail('Informe um CNPJ válido.');
        }
    }

    protected function checkDigits(string $base): string
    {
        $first = $this->digit($base, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $second = $this->digit($base.$first, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

        return $first.$second;
    }

    /** @param int[] $weights */
    protected function digit(string $chars, array $weights): string
    {
        $sum = 0;
        foreach (str_split($chars) as $i => $char) {
            $sum += (ord($char) - 48) * $weights[$i];
        }

        $remainder = $sum % 11;

        return (string) ($remainder < 2 ? 0 : 11 - $remainder);
    }
}
