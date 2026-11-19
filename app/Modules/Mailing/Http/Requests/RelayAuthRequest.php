<?php

namespace App\Modules\Mailing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Chamada do servidor SMTP do relay (smtp-relay/), não do navegador nem do cliente: autentica pelo
 * segredo compartilhado `MAIL_RELAY_SECRET`. Sem segredo configurado, nada passa.
 */
class RelayAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        $secret = config('mailing.relay.secret');

        return is_string($secret)
            && $secret !== ''
            && hash_equals($secret, (string) $this->header('X-Relay-Secret'));
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:200'],
            'remote_ip' => ['nullable', 'ip'],
        ];
    }
}
