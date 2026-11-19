<?php

namespace App\Modules\Mailing\Services\Relay;

use App\Modules\Mailing\Exceptions\RelayRejected;
use Aws\Exception\AwsException;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Mail\Transport\SesV2Transport;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;

/**
 * Entrega a mensagem do cliente como veio, pelo mailer padrão do Laravel (mesma conta, chave e
 * configuration set dos outros e-mails).
 *
 * Com o SES, o envio usa o cliente do próprio `SesV2Transport`: o `send()` dele supõe uma mensagem
 * montada pelo Laravel e quebra com MIME bruto depois de enviar, sem devolver o id. Como o envio
 * não dispara `MessageSent`, quem chama grava o rastreio (`EmailDeliveryService::recordRelayed`).
 */
class RelayMailTransport
{
    private const PERMANENT_ERRORS = ['MessageRejected', 'BadRequestException', 'MailFromDomainNotVerifiedException'];

    public function __construct(
        private MailFactory $mail,
    ) {}

    /**
     * @param  list<string>  $recipients
     * @return ?string id do SES; nulo em outro mailer (Mailpit, array), que não tem eventos para casar
     *
     * @throws RelayRejected
     */
    public function send(RelayedMessage $message, array $recipients): ?string
    {
        $transport = $this->mail->mailer()->getSymfonyTransport();

        if ($transport instanceof SesV2Transport) {
            return $this->sendThroughSes($transport, $message, $recipients);
        }

        try {
            $transport->send(new RawMessage($message->raw), new Envelope(
                new Address($message->fromAddress()),
                array_map(fn (string $email) => new Address($email), $recipients),
            ));
        } catch (TransportExceptionInterface $e) {
            Log::warning('Relay SMTP: falha no envio', ['error' => $e->getMessage()]);

            throw RelayRejected::temporaryFailure();
        }

        return null;
    }

    /** @param  list<string>  $recipients */
    private function sendThroughSes(SesV2Transport $transport, RelayedMessage $message, array $recipients): string
    {
        try {
            $result = $transport->ses()->sendEmail(array_merge($transport->getOptions(), [
                'FromEmailAddress' => $message->fromAddress(),
                'Destination' => ['ToAddresses' => $recipients],
                'Content' => ['Raw' => ['Data' => $message->raw]],
            ]));
        } catch (AwsException $e) {
            Log::warning('Relay SMTP: SES recusou', ['code' => $e->getAwsErrorCode(), 'error' => $e->getAwsErrorMessage()]);

            // Estes dependem da mensagem e não mudam numa nova tentativa. Limite de taxa, conta pausada
            // e erro do lado da AWS são do nosso lado: o cliente tenta de novo mais tarde.
            if (in_array($e->getAwsErrorCode(), self::PERMANENT_ERRORS, true)) {
                throw RelayRejected::rejectedBySes($e->getAwsErrorMessage() ?? $e->getAwsErrorCode() ?? 'erro desconhecido');
            }

            throw RelayRejected::temporaryFailure();
        }

        return (string) $result->get('MessageId');
    }
}
