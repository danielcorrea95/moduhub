<?php

namespace App\Modules\Mailing\Http\Requests;

use Illuminate\Validation\Validator;

/** Fim do DATA: o MIME em base64 e o envelope. A credencial vem de novo e é conferida de novo. */
class RelayMessageRequest extends RelayAuthRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'rcpt_to' => ['required', 'array', 'min:1', 'max:'.config('mailing.relay.max_recipients')],
            'rcpt_to.*' => ['required', 'string', 'email', 'max:254'],
            'raw' => ['required', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $validator->errors()->has('raw') && $this->rawMessage() === '') {
                    $validator->errors()->add('raw', 'Mensagem vazia ou fora de base64.');
                }
            },
        ];
    }

    public function rawMessage(): string
    {
        return (string) base64_decode((string) $this->input('raw'), true);
    }

    /** @return list<string> */
    public function recipients(): array
    {
        return array_values($this->validated('rcpt_to'));
    }
}
