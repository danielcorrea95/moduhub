<?php

namespace Tests\Feature\Mailing;

use App\Models\Tenant;
use App\Modules\Mailing\Contracts\SnsMessageVerifierContract;
use App\Modules\Mailing\Models\EmailDelivery;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class SesDeliveryTrackingTest extends TestCase
{
    use RefreshDatabase;

    private const TOPIC = 'arn:aws:sns:sa-east-1:123456789012:moduhub-ses-events';

    private Tenant $tenant;

    private bool $authentic = true;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.ses.sns_topic_arn' => self::TOPIC]);

        $this->app->instance(SnsMessageVerifierContract::class, new class($this) implements SnsMessageVerifierContract
        {
            public function __construct(private SesDeliveryTrackingTest $test) {}

            public function isAuthentic(array $message): bool
            {
                return $this->test->signatureIsAuthentic();
            }
        });

        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme-'.Str::random(4), 'status' => 'active']);
    }

    public function signatureIsAuthentic(): bool
    {
        return $this->authentic;
    }

    public function test_email_accepted_by_ses_opens_one_row_per_recipient_in_the_tenant(): void
    {
        app(CurrentTenant::class)->run($this->tenant, fn () => $this->sendThroughSes('msg-1', ['Fulano@Example.com', 'beltrano@example.com']));

        $rows = EmailDelivery::withoutTenantScope()->orderBy('recipient')->get();

        $this->assertCount(2, $rows);
        $this->assertSame(['beltrano@example.com', 'fulano@example.com'], $rows->pluck('recipient')->all());
        $this->assertSame($this->tenant->getKey(), $rows[0]->tenant_id);
        $this->assertSame(EmailDelivery::STATUS_SENT, $rows[0]->status);
        $this->assertSame('App\\Mail\\TestMail', $rows[0]->mailable);
        $this->assertSame('Documento aguarda sua assinatura', $rows[0]->subject);
    }

    public function test_email_sent_without_ses_is_not_tracked(): void
    {
        Mail::raw('Olá', fn ($message) => $message->to('fulano@example.com')->subject('Teste'));

        $this->assertSame(0, EmailDelivery::withoutTenantScope()->count());
    }

    public function test_delivery_event_marks_the_recipient_as_delivered(): void
    {
        $this->sendThroughSes('msg-1', ['fulano@example.com', 'beltrano@example.com']);

        $this->postSns($this->notification([
            'eventType' => 'Delivery',
            'mail' => ['messageId' => 'msg-1'],
            'delivery' => ['timestamp' => '2026-10-19T12:00:05.000Z', 'recipients' => ['Fulano@example.com']],
        ]))->assertNoContent();

        $delivered = $this->delivery('fulano@example.com');
        $this->assertSame(EmailDelivery::STATUS_DELIVERED, $delivered->status);
        $this->assertSame('2026-10-19 12:00:05', $delivered->delivered_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(EmailDelivery::STATUS_SENT, $this->delivery('beltrano@example.com')->status);
    }

    public function test_permanent_bounce_keeps_the_diagnostic(): void
    {
        $this->sendThroughSes('msg-1', ['naoexiste@example.com']);

        $this->postSns($this->notification([
            'notificationType' => 'Bounce',
            'mail' => ['messageId' => 'msg-1'],
            'bounce' => [
                'bounceType' => 'Permanent',
                'bounceSubType' => 'General',
                'timestamp' => '2026-10-19T12:00:05.000Z',
                'bouncedRecipients' => [
                    ['emailAddress' => 'naoexiste@example.com', 'diagnosticCode' => 'smtp; 550 5.1.1 user unknown'],
                ],
            ],
        ]))->assertNoContent();

        $row = $this->delivery('naoexiste@example.com');
        $this->assertSame(EmailDelivery::STATUS_BOUNCED, $row->status);
        $this->assertSame('Permanent/General', $row->bounce_type);
        $this->assertSame('smtp; 550 5.1.1 user unknown', $row->detail);
        $this->assertNotNull($row->bounced_at);
    }

    public function test_transient_bounce_is_only_a_delay(): void
    {
        $this->sendThroughSes('msg-1', ['cheia@example.com']);

        $this->postSns($this->notification([
            'eventType' => 'Bounce',
            'mail' => ['messageId' => 'msg-1'],
            'bounce' => [
                'bounceType' => 'Transient',
                'bounceSubType' => 'MailboxFull',
                'bouncedRecipients' => [['emailAddress' => 'cheia@example.com']],
            ],
        ]));

        $row = $this->delivery('cheia@example.com');
        $this->assertSame(EmailDelivery::STATUS_DELAYED, $row->status);
        $this->assertNull($row->bounced_at);
    }

    public function test_late_delivery_event_does_not_hide_a_complaint(): void
    {
        $this->sendThroughSes('msg-1', ['fulano@example.com']);

        $this->postSns($this->notification([
            'eventType' => 'Complaint',
            'mail' => ['messageId' => 'msg-1'],
            'complaint' => ['complaintFeedbackType' => 'abuse', 'complainedRecipients' => [['emailAddress' => 'fulano@example.com']]],
        ]));
        $this->postSns($this->notification([
            'eventType' => 'Delivery',
            'mail' => ['messageId' => 'msg-1'],
            'delivery' => ['recipients' => ['fulano@example.com']],
        ]));

        $row = $this->delivery('fulano@example.com');
        $this->assertSame(EmailDelivery::STATUS_COMPLAINED, $row->status);
        $this->assertNotNull($row->delivered_at);
        $this->assertNotNull($row->complained_at);
    }

    public function test_webhook_refuses_bad_signature_and_foreign_topic(): void
    {
        $this->sendThroughSes('msg-1', ['fulano@example.com']);
        $event = ['eventType' => 'Delivery', 'mail' => ['messageId' => 'msg-1'], 'delivery' => ['recipients' => ['fulano@example.com']]];

        $this->postSns(['TopicArn' => 'arn:aws:sns:sa-east-1:999999999999:alheio'] + $this->notification($event))->assertForbidden();

        $this->authentic = false;
        $this->postSns($this->notification($event))->assertForbidden();

        config(['services.ses.sns_topic_arn' => null]);
        $this->authentic = true;
        $this->postSns($this->notification($event))->assertForbidden();

        $this->assertSame(EmailDelivery::STATUS_SENT, $this->delivery('fulano@example.com')->status);
    }

    public function test_subscription_is_confirmed_by_visiting_the_subscribe_url(): void
    {
        Http::fake();
        $url = 'https://sns.sa-east-1.amazonaws.com/?Action=ConfirmSubscription&Token=abc';

        $this->postSns([
            'Type' => 'SubscriptionConfirmation',
            'TopicArn' => self::TOPIC,
            'Message' => 'You have chosen to subscribe to the topic.',
            'SubscribeURL' => $url,
            'Token' => 'abc',
        ])->assertNoContent();

        Http::assertSent(fn ($request) => $request->url() === $url && $request->method() === 'GET');
    }

    /** @param  list<string>  $to */
    private function sendThroughSes(string $messageId, array $to): void
    {
        $email = (new Email)
            ->from('nao-responda@moduhub.com.br')
            ->to(...$to)
            ->subject('Documento aguarda sua assinatura')
            ->text('Assine aqui.');

        // O que o SesV2Transport faz depois que a AWS aceita o envio.
        $email->getHeaders()->addTextHeader('X-SES-Message-ID', $messageId);

        event(new MessageSent(
            new SentMessage(new SymfonySentMessage($email, Envelope::create($email))),
            ['__laravel_mailable' => 'App\\Mail\\TestMail'],
        ));
    }

    /** @param  array<string, mixed>  $event */
    private function notification(array $event): array
    {
        return [
            'Type' => 'Notification',
            'MessageId' => (string) Str::uuid(),
            'TopicArn' => self::TOPIC,
            'Message' => json_encode($event),
            'Timestamp' => now()->toIso8601String(),
            'SignatureVersion' => '2',
            'Signature' => 'assinatura',
            'SigningCertURL' => 'https://sns.sa-east-1.amazonaws.com/SimpleNotificationService.pem',
        ];
    }

    /** @param  array<string, mixed>  $payload */
    private function postSns(array $payload)
    {
        return $this->call('POST', '/webhooks/ses', server: ['CONTENT_TYPE' => 'text/plain; charset=UTF-8'], content: json_encode($payload));
    }

    private function delivery(string $recipient): EmailDelivery
    {
        return EmailDelivery::withoutTenantScope()->where('recipient', $recipient)->firstOrFail();
    }
}
