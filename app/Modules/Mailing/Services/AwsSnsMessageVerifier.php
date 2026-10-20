<?php

namespace App\Modules\Mailing\Services;

use App\Modules\Mailing\Contracts\SnsMessageVerifierContract;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use Throwable;

class AwsSnsMessageVerifier implements SnsMessageVerifierContract
{
    public function __construct(
        private MessageValidator $validator = new MessageValidator,
    ) {}

    public function isAuthentic(array $message): bool
    {
        try {
            return $this->validator->isValid(Message::fromArray($message));
        } catch (Throwable) {
            return false;
        }
    }
}
