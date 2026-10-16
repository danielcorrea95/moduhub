<?php

namespace App\Modules\Invoices\Http\Requests\Api;

use App\Modules\Invoices\Http\Requests\UpdateIssuerRequest;

/** Mesmas regras do painel; a autorização na API vem da chave e dos escopos, não do usuário logado. */
class UpdateIssuerApiRequest extends UpdateIssuerRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
