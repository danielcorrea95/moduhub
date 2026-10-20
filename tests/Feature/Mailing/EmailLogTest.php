<?php

namespace Tests\Feature\Mailing;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Mailing\Contracts\SuppressionListGatewayContract;
use App\Modules\Mailing\Models\EmailDelivery;
use App\Modules\Mailing\Models\EmailDeliveryEvent;
use App\Modules\Mailing\Models\EmailSuppression;
use App\Modules\Mailing\Services\EmailDeliveryService;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class EmailLogTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme-'.Str::random(4), 'status' => 'active']);
        $this->tenant->users()->attach($this->owner, ['role' => 'owner']);
    }

    public function test_send_records_provider_sender_and_history(): void
    {
        $this->send('msg-1', 'Fulano@Gmail.com');

        $row = $this->delivery('fulano@gmail.com');
        $this->assertSame('google', $row->provider);
        $this->assertSame('contato@acme.com.br', $row->from_address);
        $this->assertSame([EmailDeliveryEvent::TYPE_SENT], $row->events->pluck('type')->all());
    }

    public function test_open_and_click_are_counted_once_per_sns_delivery_and_links_lose_their_token(): void
    {
        $this->send('msg-1', 'fulano@gmail.com');
        $open = ['eventType' => 'Open', 'mail' => ['messageId' => 'msg-1'], 'open' => ['timestamp' => '2026-10-20T13:00:00.123Z', 'userAgent' => 'Mozilla/5.0']];

        $this->apply($open);
        $this->apply($open); // SNS reentrega
        $this->apply(['eventType' => 'Open', 'mail' => ['messageId' => 'msg-1'], 'open' => ['timestamp' => '2026-10-20T14:00:00.000Z']]);
        $this->apply(['eventType' => 'Click', 'mail' => ['messageId' => 'msg-1'], 'click' => [
            'timestamp' => '2026-10-20T14:01:00.000Z',
            'link' => 'https://app.moduhub.com.br/sign/abc123?token=segredo',
        ]]);

        $row = $this->delivery('fulano@gmail.com');
        $this->assertSame(2, $row->open_count);
        $this->assertSame('2026-10-20 13:00:00', $row->opened_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(1, $row->click_count);
        $this->assertSame('https://app.moduhub.com.br/sign/abc123', $row->events->firstWhere('type', 'clicked')->detail);
        // Abrir não muda a situação da entrega.
        $this->assertSame(EmailDelivery::STATUS_SENT, $row->status);
    }

    public function test_permanent_bounce_and_complaint_go_to_the_block_list_but_transient_does_not(): void
    {
        $this->send('msg-1', 'naoexiste@acme.test');
        $this->send('msg-2', 'cheia@acme.test');
        $this->send('msg-3', 'reclamou@acme.test');

        $this->apply(['eventType' => 'Bounce', 'mail' => ['messageId' => 'msg-1'], 'bounce' => [
            'bounceType' => 'Permanent', 'bounceSubType' => 'General',
            'bouncedRecipients' => [['emailAddress' => 'naoexiste@acme.test', 'diagnosticCode' => '550 user unknown']],
        ]]);
        $this->apply(['eventType' => 'Bounce', 'mail' => ['messageId' => 'msg-2'], 'bounce' => [
            'bounceType' => 'Transient', 'bounceSubType' => 'MailboxFull',
            'bouncedRecipients' => [['emailAddress' => 'cheia@acme.test']],
        ]]);
        $this->apply(['eventType' => 'Complaint', 'mail' => ['messageId' => 'msg-3'], 'complaint' => [
            'complaintFeedbackType' => 'abuse', 'complainedRecipients' => [['emailAddress' => 'reclamou@acme.test']],
        ]]);

        $list = EmailSuppression::withoutTenantScope()->orderBy('email')->get();
        $this->assertSame(['naoexiste@acme.test', 'reclamou@acme.test'], $list->pluck('email')->all());
        $this->assertSame([EmailSuppression::REASON_BOUNCE, EmailSuppression::REASON_COMPLAINT], $list->pluck('reason')->all());
        $this->assertSame($this->tenant->getKey(), $list[0]->tenant_id);
    }

    public function test_bounced_address_can_be_released_but_spam_complaint_cannot(): void
    {
        $bounce = EmailSuppression::withoutTenantScope()->create(['tenant_id' => $this->tenant->getKey(), 'email' => 'naoexiste@acme.test', 'reason' => 'bounce']);
        $spam = EmailSuppression::withoutTenantScope()->create(['tenant_id' => $this->tenant->getKey(), 'email' => 'reclamou@acme.test', 'reason' => 'complaint']);

        $this->asOwner()->delete(route('emails.suppressions.release', $bounce))->assertRedirect(route('emails.suppressions'));
        $this->asOwner()->delete(route('emails.suppressions.release', $spam))->assertSessionHasErrors('suppression');

        $this->assertSame(['reclamou@acme.test'], EmailSuppression::withoutTenantScope()->pluck('email')->all());
        $this->assertSame(['naoexiste@acme.test'], app(SuppressionListGatewayContract::class)->removed);
    }

    public function test_overview_counts_the_period_with_rates(): void
    {
        $this->send('msg-1', 'a@gmail.com');
        $this->send('msg-2', 'b@gmail.com');
        $this->send('msg-3', 'c@hotmail.com');
        $this->send('msg-4', 'd@hotmail.com');
        foreach (['msg-1' => 'a@gmail.com', 'msg-2' => 'b@gmail.com', 'msg-3' => 'c@hotmail.com'] as $id => $to) {
            $this->apply(['eventType' => 'Delivery', 'mail' => ['messageId' => $id], 'delivery' => ['recipients' => [$to]]]);
        }
        $this->apply(['eventType' => 'Open', 'mail' => ['messageId' => 'msg-1'], 'open' => []]);
        $this->apply(['eventType' => 'Bounce', 'mail' => ['messageId' => 'msg-4'], 'bounce' => [
            'bounceType' => 'Permanent', 'bouncedRecipients' => [['emailAddress' => 'd@hotmail.com']],
        ]]);

        $this->asOwner()->get(route('emails.overview', ['periodo' => 7]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mailing/Emails/Overview')
                ->where('period.days', 7)
                ->where('report.totals.sent', 4)
                ->where('report.totals.delivered', 3)
                ->where('report.totals.opened', 1)
                ->where('report.totals.bounced', 1)
                ->where('report.totals.rates.delivered', 75)
                ->where('report.totals.rates.opened', 33.3)
                ->where('report.totals.rates.bounced', 25)
                ->where('report.suppressed', 1)
                ->has('report.daily', 7)
                ->where('report.daily.6.sent', 4)
                ->has('report.providers', 2)
                ->where('report.providers.0.label', 'Gmail'));
    }

    public function test_messages_filter_and_detail_stay_inside_the_workspace(): void
    {
        $this->send('msg-1', 'fulano@gmail.com');
        $this->send('msg-2', 'beltrano@hotmail.com');
        $this->apply(['eventType' => 'Delivery', 'mail' => ['messageId' => 'msg-1'], 'delivery' => ['recipients' => ['fulano@gmail.com']]]);

        $other = Tenant::create(['name' => 'Outra', 'slug' => 'outra-'.Str::random(4), 'status' => 'active']);
        app(CurrentTenant::class)->run($other, fn () => $this->sendRaw('msg-9', 'intruso@gmail.com'));
        $foreign = EmailDelivery::withoutTenantScope()->where('recipient', 'intruso@gmail.com')->first();
        $mine = $this->delivery('fulano@gmail.com');

        $this->asOwner()->get(route('emails.messages', ['status' => 'delivered', 'mensagem' => $mine->getKey()]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mailing/Emails/Messages')
                ->has('messages.data', 1)
                ->where('messages.data.0.recipient', 'fulano@gmail.com')
                ->where('messages.data.0.provider', 'Gmail')
                ->where('selected.recipient', 'fulano@gmail.com')
                ->where('selected.events.1.type', 'delivered'));

        $this->asOwner()->get(route('emails.messages', ['busca' => 'intruso', 'mensagem' => $foreign->getKey()]))
            ->assertInertia(fn (Assert $page) => $page->has('messages.data', 0)->where('selected', null));
    }

    public function test_members_cannot_see_who_received_what(): void
    {
        $member = User::factory()->create();
        $this->tenant->users()->attach($member, ['role' => 'member']);

        $this->actingAs($member)->withSession(['current_tenant_id' => $this->tenant->getKey()])
            ->get(route('emails.messages'))
            ->assertForbidden();
    }

    private function asOwner(): static
    {
        return $this->actingAs($this->owner)->withSession(['current_tenant_id' => $this->tenant->getKey()]);
    }

    private function send(string $messageId, string $to): void
    {
        app(CurrentTenant::class)->run($this->tenant, fn () => $this->sendRaw($messageId, $to));
    }

    private function sendRaw(string $messageId, string $to): void
    {
        $email = (new Email)->from('Acme <contato@acme.com.br>')->to($to)->subject('Contrato aguarda sua assinatura')->html('<p>Assine</p>');
        $email->getHeaders()->addTextHeader('X-SES-Message-ID', $messageId);

        event(new MessageSent(
            new SentMessage(new SymfonySentMessage($email, Envelope::create($email))),
            ['__laravel_mailable' => 'App\\Modules\\Signatures\\Mail\\DocumentReadyMail'],
        ));
    }

    /** @param  array<string, mixed>  $event */
    private function apply(array $event): void
    {
        app(EmailDeliveryService::class)->applyEvent($event);
    }

    private function delivery(string $recipient): EmailDelivery
    {
        return EmailDelivery::withoutTenantScope()->with(['events' => fn ($q) => $q->withoutTenantScope()])->where('recipient', $recipient)->firstOrFail();
    }
}
