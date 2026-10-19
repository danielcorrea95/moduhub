<?php

namespace App\Modules\Invoices\Http\Requests;

/** Mesmos campos do cancelamento (cMotivo 1, 2 ou 9 e justificativa de 15 a 255), mesma alçada. */
class CancellationReviewRequest extends CancelInvoiceRequest {}
