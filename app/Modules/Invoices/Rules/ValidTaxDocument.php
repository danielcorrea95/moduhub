<?php

namespace App\Modules\Invoices\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CPF ou CNPJ do tomador. O leiaute v1.01 da DPS ainda exige CNPJ numérico, então o formato
 * alfanumérico é recusado aqui mesmo com dígitos verificadores corretos.
 */
class ValidTaxDocument implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $document = (string) $value;

        if (strlen($document) === 11 && ctype_digit($document)) {
            if (! $this->validCpf($document)) {
                $fail('Informe um CPF válido.');
            }

            return;
        }

        if (strlen($document) === 14 && ! ctype_digit($document)) {
            $fail('O leiaute atual da NFS-e Nacional ainda não aceita CNPJ alfanumérico.');

            return;
        }

        if (strlen($document) !== 14) {
            $fail('Informe um CPF (11 dígitos) ou CNPJ (14 dígitos).');

            return;
        }

        (new ValidCnpj)->validate($attribute, $document, $fail);
    }

    protected function validCpf(string $cpf): bool
    {
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($position = 9; $position < 11; $position++) {
            $sum = 0;
            for ($i = 0; $i < $position; $i++) {
                $sum += (int) $cpf[$i] * ($position + 1 - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$position] !== $digit) {
                return false;
            }
        }

        return true;
    }
}
