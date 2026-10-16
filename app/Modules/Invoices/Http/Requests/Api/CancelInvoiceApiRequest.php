<?php

namespace App\Modules\Invoices\Http\Requests\Api;

use App\Modules\Invoices\Http\Requests\CancelInvoiceRequest;

class CancelInvoiceApiRequest extends CancelInvoiceRequest
{
    /** A chave de API e o escopo já foram verificados pelos middlewares api.key e api.scope. */
    public function authorize(): bool
    {
        return true;
    }
}
