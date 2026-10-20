<?php

namespace App\Modules\Mailing\Services;

use App\Modules\Mailing\Contracts\EmailDeliveryRepositoryContract;
use App\Modules\Mailing\Enums\MailboxProvider;
use App\Modules\Mailing\Models\EmailDelivery;
use App\Modules\Mailing\Models\EmailDeliveryEvent;
use App\Modules\Mailing\Models\EmailSuppression;
use App\Modules\Mailing\Services\Suppression\EmailSuppressionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EmailDeliveryService
{
    public function __construct(
        private EmailDeliveryRepositoryContract $deliveries,
        private EmailSuppressionService $suppressions,
    ) {}

    /**
     * Abre uma linha por destinatário assim que o SES aceita o e-mail. Sem o id do SES (mailer
     * log, smtp, array) não há evento para casar depois, e nada é gravado.
     */
    public function recordSent(Email $message, ?string $mailable = null): void
    {
        $messageId = $message->getHeaders()->get('X-SES-Message-ID')?->getBodyAsString();

        if (! $messageId) {
            return;
        }

        $recipients = array_merge($message->getTo(), $message->getCc(), $message->getBcc());
        $from = $message->getFrom()[0] ?? null;
        $sentAt = now();

        foreach ($recipients as $address) {
            $recipient = $this->normalize($address);

            $delivery = $this->deliveries->record([
                'message_id' => $messageId,
                'recipient' => $recipient,
                'from_address' => $from ? $this->normalize($from) : null,
                'provider' => MailboxProvider::fromEmail($recipient)->value,
                'mailable' => $mailable,
                'subject' => Str::limit((string) $message->getSubject(), 250),
                'status' => EmailDelivery::STATUS_SENT,
                'sent_at' => $sentAt,
            ]);

            $this->deliveries->recordEvent($delivery, EmailDeliveryEvent::TYPE_SENT, $sentAt);
        }
    }

    /**
     * Aplica um evento do SES (publicação do configuration set ou notificação da identidade).
     *
     * @param  array<string, mixed>  $event
     */
    public function applyEvent(array $event): void
    {
        $type = $event['eventType'] ?? $event['notificationType'] ?? null;
        $messageId = $event['mail']['messageId'] ?? null;

        if (! is_string($messageId)) {
            return;
        }

        match ($type) {
            'Delivery' => $this->applyDelivery($messageId, $event['delivery'] ?? []),
            'Bounce' => $this->applyBounce($messageId, $event['bounce'] ?? []),
            'Complaint' => $this->applyComplaint($messageId, $event['complaint'] ?? []),
            'Reject' => $this->applyReject($messageId, $event['mail']['destination'] ?? [], $event['reject'] ?? []),
            'DeliveryDelay' => $this->applyDelay($messageId, $event['deliveryDelay'] ?? []),
            'Open' => $this->applyOpen($messageId, $event['open'] ?? []),
            'Click' => $this->applyClick($messageId, $event['click'] ?? []),
            default => null,
        };
    }

    private function applyDelivery(string $messageId, array $delivery): void
    {
        foreach ($delivery['recipients'] ?? [] as $recipient) {
            $this->transition($messageId, $recipient, EmailDelivery::STATUS_DELIVERED, [
                'delivered_at' => $this->time($delivery['timestamp'] ?? null),
            ], $this->time($delivery['timestamp'] ?? null), $delivery['smtpResponse'] ?? null);
        }
    }

    private function applyBounce(string $messageId, array $bounce): void
    {
        // Bounce transitório (caixa cheia, servidor fora) ainda pode ser entregue nas retentativas do SES.
        $status = ($bounce['bounceType'] ?? null) === 'Transient'
            ? EmailDelivery::STATUS_DELAYED
            : EmailDelivery::STATUS_BOUNCED;

        $bounceType = trim(($bounce['bounceType'] ?? '').'/'.($bounce['bounceSubType'] ?? ''), '/');

        $at = $this->time($bounce['timestamp'] ?? null);

        foreach ($bounce['bouncedRecipients'] ?? [] as $recipient) {
            $detail = $recipient['diagnosticCode'] ?? $bounceType ?: null;

            $row = $this->transition($messageId, $recipient['emailAddress'] ?? '', $status, [
                'bounce_type' => $bounceType ?: null,
                'detail' => $recipient['diagnosticCode'] ?? null,
                'bounced_at' => $status === EmailDelivery::STATUS_BOUNCED ? $at : null,
            ], $at, $detail);

            if ($row !== null && $status === EmailDelivery::STATUS_BOUNCED) {
                $this->suppressions->suppress($row, EmailSuppression::REASON_BOUNCE, $detail);
            }
        }
    }

    private function applyComplaint(string $messageId, array $complaint): void
    {
        $at = $this->time($complaint['timestamp'] ?? null);
        $detail = $complaint['complaintFeedbackType'] ?? null;

        foreach ($complaint['complainedRecipients'] ?? [] as $recipient) {
            $row = $this->transition($messageId, $recipient['emailAddress'] ?? '', EmailDelivery::STATUS_COMPLAINED, [
                'detail' => $detail,
                'complained_at' => $at,
            ], $at, $detail);

            if ($row !== null) {
                $this->suppressions->suppress($row, EmailSuppression::REASON_COMPLAINT, $detail);
            }
        }
    }

    private function applyReject(string $messageId, array $destinations, array $reject): void
    {
        foreach ($destinations as $recipient) {
            $this->transition($messageId, $recipient, EmailDelivery::STATUS_REJECTED, [
                'detail' => $reject['reason'] ?? null,
            ], now(), $reject['reason'] ?? null);
        }
    }

    private function applyDelay(string $messageId, array $delay): void
    {
        $at = $this->time($delay['timestamp'] ?? null);

        foreach ($delay['delayedRecipients'] ?? [] as $recipient) {
            $detail = $recipient['diagnosticCode'] ?? $delay['delayType'] ?? null;

            $this->transition($messageId, $recipient['emailAddress'] ?? '', EmailDelivery::STATUS_DELAYED, [
                'detail' => $detail,
            ], $at, $detail);
        }
    }

    /**
     * O SES manda a abertura por mensagem, sem dizer quem abriu. Com mais de um destinatário,
     * todos ganham a marca. A abertura também é aproximada: o Apple Mail baixa o pixel sozinho.
     */
    private function applyOpen(string $messageId, array $open): void
    {
        $at = $this->time($open['timestamp'] ?? null);

        foreach ($this->deliveries->forMessage($messageId) as $delivery) {
            if (! $this->deliveries->recordEvent($delivery, EmailDeliveryEvent::TYPE_OPENED, $at, $open['userAgent'] ?? null)) {
                continue;
            }

            $delivery->opened_at = $delivery->opened_at === null || $at->lt($delivery->opened_at) ? $at : $delivery->opened_at;
            $delivery->open_count++;
            $this->deliveries->save($delivery);
        }
    }

    private function applyClick(string $messageId, array $click): void
    {
        $at = $this->time($click['timestamp'] ?? null);

        foreach ($this->deliveries->forMessage($messageId) as $delivery) {
            if (! $this->deliveries->recordEvent($delivery, EmailDeliveryEvent::TYPE_CLICKED, $at, $this->safeLink($click['link'] ?? null))) {
                continue;
            }

            $delivery->clicked_at = $delivery->clicked_at === null || $at->lt($delivery->clicked_at) ? $at : $delivery->clicked_at;
            $delivery->click_count++;
            $this->deliveries->save($delivery);
        }
    }

    /** @param  array<string, mixed>  $attributes */
    private function transition(string $messageId, string $recipient, string $status, array $attributes, Carbon $at, ?string $detail): ?EmailDelivery
    {
        $delivery = $this->deliveries->findByMessageAndRecipient($messageId, $this->normalize($recipient));

        if ($delivery === null) {
            return null;
        }

        $this->deliveries->recordEvent($delivery, $status, $at, $detail);

        // As datas sempre ficam registradas; o status só avança.
        $delivery->fill(array_filter($attributes, fn ($value) => $value !== null));

        if (! $delivery->outranks($status)) {
            $delivery->status = $status;
        }

        if (isset($attributes['detail'])) {
            $delivery->detail = Str::limit((string) $attributes['detail'], 495);
        }

        return $this->deliveries->save($delivery);
    }

    /**
     * Link de assinatura e de download carrega token na query. O histórico guarda só o endereço,
     * para ninguém do workspace copiar do painel o link de outra pessoa.
     */
    private function safeLink(?string $link): ?string
    {
        if ($link === null) {
            return null;
        }

        $parts = parse_url($link);

        if (! isset($parts['host'])) {
            return null;
        }

        return ($parts['scheme'] ?? 'https').'://'.$parts['host'].($parts['path'] ?? '');
    }

    private function normalize(Address|string $address): string
    {
        $email = $address instanceof Address ? $address->getAddress() : $address;

        // O SES pode devolver "Nome <email>" em mail.destination.
        if (preg_match('/<([^>]+)>/', $email, $match)) {
            $email = $match[1];
        }

        return Str::lower(trim($email));
    }

    private function time(?string $value): Carbon
    {
        return $value ? Carbon::parse($value) : now();
    }
}
