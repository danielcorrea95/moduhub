<?php

namespace App\Modules\Mailing\Http\Requests;

use App\Modules\Mailing\Contracts\SnsMessageVerifierContract;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST do SNS com eventos do SES. Rota pública: quem autentica é a assinatura da AWS, e só o
 * tópico configurado é aceito, para ninguém inscrever o próprio tópico no nosso endpoint.
 */
class SesNotificationRequest extends FormRequest
{
    /** @var array<string, mixed> */
    private array $snsMessage = [];

    protected function prepareForValidation(): void
    {
        // O SNS manda JSON com Content-Type text/plain.
        $this->snsMessage = json_decode($this->getContent(), true) ?: [];

        $this->replace($this->snsMessage);
    }

    public function authorize(): bool
    {
        $topic = config('services.ses.sns_topic_arn');

        return is_string($topic)
            && $topic !== ''
            && ($this->snsMessage['TopicArn'] ?? null) === $topic
            && app(SnsMessageVerifierContract::class)->isAuthentic($this->snsMessage);
    }

    public function rules(): array
    {
        return [
            'Type' => ['required', 'in:Notification,SubscriptionConfirmation,UnsubscribeConfirmation'],
            'TopicArn' => ['required', 'string'],
            'Message' => ['required', 'string'],
            'SubscribeURL' => ['required_if:Type,SubscriptionConfirmation', 'url:https'],
        ];
    }

    /** @return array<string, mixed> */
    public function snsMessage(): array
    {
        return $this->snsMessage;
    }
}
