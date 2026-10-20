<?php

namespace App\Modules\Mailing\Contracts;

interface SnsMessageVerifierContract
{
    /**
     * Confere a assinatura da mensagem com o certificado publicado pela AWS.
     *
     * @param  array<string, mixed>  $message  corpo JSON do POST do SNS, sem alteração
     */
    public function isAuthentic(array $message): bool;
}
