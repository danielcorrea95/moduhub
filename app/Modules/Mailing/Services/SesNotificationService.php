<?php

namespace App\Modules\Mailing\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Recebe o que o SNS entrega no webhook do SES. A mensagem chega já com assinatura e tópico
 * conferidos pelo SesNotificationRequest.
 */
class SesNotificationService
{
    public function __construct(
        private EmailDeliveryService $deliveries,
    ) {}

    /** @param  array<string, mixed>  $message */
    public function handle(array $message): void
    {
        match ($message['Type']) {
            'SubscriptionConfirmation' => $this->confirmSubscription($message),
            'Notification' => $this->deliveries->applyEvent(json_decode($message['Message'], true) ?: []),
            'UnsubscribeConfirmation' => Log::warning('SES: tópico SNS cancelou a inscrição do webhook.', [
                'topic' => $message['TopicArn'],
            ]),
        };
    }

    /** @param  array<string, mixed>  $message */
    private function confirmSubscription(array $message): void
    {
        Http::timeout(10)->get($message['SubscribeURL'])->throw();

        Log::info('SES: inscrição do webhook no tópico SNS confirmada.', ['topic' => $message['TopicArn']]);
    }
}
