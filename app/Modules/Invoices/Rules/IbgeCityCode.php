<?php

namespace App\Modules\Invoices\Rules;

use App\Modules\Invoices\Services\Nfse\Municipalities;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Código de município que existe na tabela do IBGE (a Sefin rejeita código inexistente). */
class IbgeCityCode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Municipalities::exists((string) $value)) {
            $fail('O código IBGE informado não corresponde a nenhum município.');
        }
    }
}
