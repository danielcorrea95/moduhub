<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Http\Requests\AsaasWebhookRequest;
use App\Modules\Billing\Services\AsaasWebhookService;
use Illuminate\Http\JsonResponse;

class AsaasWebhookController extends Controller
{
    public function __invoke(AsaasWebhookRequest $request, AsaasWebhookService $webhooks): JsonResponse
    {
        $webhooks->handle($request->all());

        // Qualquer resposta fora de 2xx faz o Asaas reenviar e, depois de várias, pausar a fila.
        return response()->json(['received' => true]);
    }
}
