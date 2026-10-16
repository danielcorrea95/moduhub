<?php

namespace App\Modules\Signatures\Http\Requests\Api;

use App\Modules\Signatures\Http\Requests\StoreSignerRequest;

/** Mesmas regras do painel; a autorização vem da chave de API (escopo signatures:write). */
class StoreSignerApiRequest extends StoreSignerRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
