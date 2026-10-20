<?php

namespace App\Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST do Asaas. Rota pública: autentica pelo token fixo que cadastramos no webhook do Asaas e que
 * volta no header asaas-access-token. Sem token configurado, recusa tudo.
 */
class AsaasWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expected = config('services.asaas.webhook_token');
        $received = $this->header('asaas-access-token');

        return is_string($expected) && $expected !== '' && is_string($received) && hash_equals($expected, $received);
    }

    public function rules(): array
    {
        return [
            'event' => ['required', 'string', 'max:100'],
            'id' => ['nullable', 'string', 'max:100'],
            'payment' => ['nullable', 'array'],
            'payment.id' => ['required_with:payment', 'string'],
            'payment.customer' => ['required_with:payment', 'string'],
        ];
    }
}
