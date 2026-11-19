<?php

namespace Tests\Feature\Mailing;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Mailing\Exceptions\RelayRejected;
use App\Modules\Mailing\Models\EmailDelivery;
use App\Modules\Mailing\Models\EmailSuppression;
use App\Modules\Mailing\Models\SmtpCredential;
use App\Modules\Mailing\Services\Quota\EmailQuotaService;
use App\Modules\Mailing\Services\Relay\RelayedMessage;
use App\Modules\Mailing\Services\Relay\RelayMailTransport;
use App\Modules\Mailing\Services\SmtpCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Mailer\SentMessage;
use Tests\TestCase;

class SmtpRelayTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'segredo-do-relay';

    private Tenant $tenant;

    private User $owner;

    private string $username;

    private string $password;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mailing.relay.secret' => self::SECRET,
            'mailing.quota.free' => ['daily' => 100, 'monthly' => 3000],
        ]);

        $this->owner = User::factory()->create();
        $this->tenant = Tenant::create(['name' => 'Empresa X', 'slug' => 'empresa-x-'.Str::random(4), 'status' => 'active']);
        $this->tenant->users()->attach($this->owner, ['role' => 'owner']);

        $this->asOwner()->post(route('sending-domain.store'), [
            'domain' => 'empresax.com.br',
            'from_name' => 'Empresa X',
            'from_local_part' => 'contato',
        ])->assertSessionHasNoErrors();
        $this->asOwner()->post(route('sending-domain.verify'));

        ['credential' => $credential, 'password' => $this->password] = app(SmtpCredentialService::class)
            ->issue($this->tenant, $this->owner, 'Site');
        $this->username = $credential->username;
    }

    public function test_internal_endpoints_require_the_relay_secret(): void
    {
        $body = ['username' => $this->username, 'password' => $this->password];

        $this->postJson(route('internal.smtp-relay.auth'), $body)->assertForbidden();
        $this->postJson(route('internal.smtp-relay.auth'), $body, ['X-Relay-Secret' => 'outro'])->assertForbidden();

        config(['mailing.relay.secret' => null]);
        $this->postJson(route('internal.smtp-relay.auth'), $body, ['X-Relay-Secret' => ''])->assertForbidden();
    }

    public function test_authenticates_the_credential_and_throttles_wrong_passwords(): void
    {
        $this->auth($this->password)->assertOk()->assertJson(['ok' => true, 'tenant_id' => $this->tenant->getKey()]);

        $this->auth('senha-errada')->assertStatus(422)->assertJson(['smtp_code' => 535, 'enhanced_code' => '5.7.8']);

        foreach (range(2, config('mailing.relay.auth_attempts')) as $_) {
            $this->auth('senha-errada');
        }

        // Bloqueado pelo IP, mesmo com a senha certa.
        $this->auth($this->password)->assertStatus(422)->assertJson(['smtp_code' => 454]);
    }

    public function test_relays_the_message_and_counts_one_per_message(): void
    {
        $raw = $this->mime(extraHeaders: "Bcc: oculto@cliente.com\r\nReturn-Path: <bounce@outro.com>\r\n");

        $this->deliver($raw, ['ana@cliente.com', 'Bia@Cliente.com', 'oculto@cliente.com'])
            ->assertOk()
            ->assertJson(['ok' => true, 'message_id' => null]);

        /** @var SentMessage $sent */
        $sent = app('mail.manager')->mailer()->getSymfonyTransport()->messages()->sole();
        $this->assertStringNotContainsString('oculto@cliente.com', $sent->toString());
        $this->assertStringNotContainsString('Return-Path', $sent->toString());
        $this->assertStringContainsString('Olá do sistema do cliente', $sent->toString());
        $this->assertSame(
            ['ana@cliente.com', 'bia@cliente.com', 'oculto@cliente.com'],
            array_map(fn ($a) => $a->getAddress(), $sent->getEnvelope()->getRecipients()),
        );

        $this->assertSame(1, app(EmailQuotaService::class)->usage($this->tenant)->dailyUsed);
        $this->assertNotNull(SmtpCredential::withoutTenantScope()->sole()->last_used_at);
    }

    public function test_sender_must_belong_to_the_verified_domain(): void
    {
        $this->deliver($this->mime(from: 'Golpe <financeiro@banco.com.br>'))
            ->assertStatus(422)
            ->assertJson(['smtp_code' => 550, 'enhanced_code' => '5.7.1']);

        $this->deliver($this->mime(extraHeaders: "Sender: outro@banco.com.br\r\n"))
            ->assertStatus(422)
            ->assertJson(['smtp_code' => 550]);

        $this->assertSame(0, app(EmailQuotaService::class)->usage($this->tenant)->dailyUsed);
    }

    public function test_suppressed_recipients_are_skipped(): void
    {
        EmailSuppression::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->getKey(),
            'email' => 'devolveu@cliente.com',
            'reason' => EmailSuppression::REASON_BOUNCE,
        ]);

        $this->deliver($this->mime(), ['devolveu@cliente.com'])
            ->assertStatus(422)
            ->assertJson(['smtp_code' => 550, 'enhanced_code' => '5.1.1']);

        $this->deliver($this->mime(), ['devolveu@cliente.com', 'ana@cliente.com'])->assertOk();

        $sent = app('mail.manager')->mailer()->getSymfonyTransport()->messages()->sole();
        $this->assertSame(['ana@cliente.com'], array_map(fn ($a) => $a->getAddress(), $sent->getEnvelope()->getRecipients()));
    }

    public function test_quota_blocks_the_message(): void
    {
        config(['mailing.quota.free' => ['daily' => 1, 'monthly' => 3000]]);

        $this->deliver($this->mime())->assertOk();
        $this->deliver($this->mime())
            ->assertStatus(422)
            ->assertJson(['smtp_code' => 550, 'enhanced_code' => '5.4.5'])
            ->assertJsonPath('message', 'Limite diário de 1 e-mails atingido. O envio volta à meia-noite (horário de Brasília).');
    }

    public function test_failed_send_gives_the_quota_back(): void
    {
        $this->mock(RelayMailTransport::class)
            ->shouldReceive('send')
            ->andThrow(RelayRejected::temporaryFailure());

        $this->deliver($this->mime())->assertStatus(422)->assertJson(['smtp_code' => 451]);

        $this->assertSame(0, app(EmailQuotaService::class)->usage($this->tenant)->dailyUsed);
    }

    public function test_ses_message_is_tracked_in_the_workspace_history(): void
    {
        $this->mock(RelayMailTransport::class)
            ->shouldReceive('send')
            ->withArgs(fn (RelayedMessage $message, array $recipients) => $recipients === ['ana@cliente.com'])
            ->andReturn('ses-123');

        $this->deliver($this->mime(subject: '=?UTF-8?B?'.base64_encode('Pedido nº 42 confirmado').'?='), ['ana@cliente.com'])
            ->assertOk()
            ->assertJson(['message_id' => 'ses-123']);

        $delivery = EmailDelivery::withoutTenantScope()->sole();
        $this->assertSame($this->tenant->getKey(), $delivery->tenant_id);
        $this->assertSame('ana@cliente.com', $delivery->recipient);
        $this->assertSame('contato@empresax.com.br', $delivery->from_address);
        $this->assertSame('Pedido nº 42 confirmado', $delivery->subject);
        $this->assertSame('SMTP do cliente', $delivery->originLabel());
    }

    public function test_invalid_payload_is_refused(): void
    {
        $this->postJson(route('internal.smtp-relay.messages'), [
            'username' => $this->username,
            'password' => $this->password,
            'rcpt_to' => ['ana@cliente.com'],
            'raw' => 'isso não é base64!',
        ], ['X-Relay-Secret' => self::SECRET])->assertUnprocessable()->assertJsonValidationErrors('raw');

        $this->deliver("Sem cabeçalho\r\n\r\ncorpo")->assertStatus(422)->assertJson(['smtp_code' => 550, 'enhanced_code' => '5.6.0']);
    }

    private function auth(string $password): TestResponse
    {
        return $this->postJson(route('internal.smtp-relay.auth'), [
            'username' => $this->username,
            'password' => $password,
            'remote_ip' => '203.0.113.10',
        ], ['X-Relay-Secret' => self::SECRET]);
    }

    /** @param  list<string>  $recipients */
    private function deliver(string $raw, array $recipients = ['ana@cliente.com']): TestResponse
    {
        return $this->postJson(route('internal.smtp-relay.messages'), [
            'username' => $this->username,
            'password' => $this->password,
            'remote_ip' => '203.0.113.10',
            'rcpt_to' => $recipients,
            'raw' => base64_encode($raw),
        ], ['X-Relay-Secret' => self::SECRET]);
    }

    private function mime(string $from = '"Empresa X, Vendas" <contato@EmpresaX.com.br>', string $subject = 'Seu pedido', string $extraHeaders = ''): string
    {
        return "From: {$from}\r\n"
            ."To: ana@cliente.com\r\n"
            .$extraHeaders
            ."Subject: {$subject}\r\n"
            ."MIME-Version: 1.0\r\n"
            ."Content-Type: text/plain; charset=UTF-8\r\n"
            ."\r\n"
            ."Olá do sistema do cliente.\r\n";
    }

    private function asOwner(): static
    {
        return $this->actingAs($this->owner)->withSession(['current_tenant_id' => $this->tenant->getKey()]);
    }
}
