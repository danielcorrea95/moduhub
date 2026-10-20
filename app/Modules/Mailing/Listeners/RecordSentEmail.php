<?php

namespace App\Modules\Mailing\Listeners;

use App\Modules\Mailing\Services\EmailDeliveryService;
use Illuminate\Mail\Events\MessageSent;
use Throwable;

class RecordSentEmail
{
    public function __construct(
        private EmailDeliveryService $deliveries,
    ) {}

    public function handle(MessageSent $event): void
    {
        try {
            $this->deliveries->recordSent(
                $event->message,
                $event->data['__laravel_mailable'] ?? $event->data['__laravel_notification'] ?? null,
            );
        } catch (Throwable $e) {
            // O e-mail já saiu. Deixar a exceção subir faria o job da fila tentar de novo e mandar
            // o mesmo e-mail duas vezes; perder a linha de rastreio é o mal menor.
            report($e);
        }
    }
}
