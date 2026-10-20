<?php

namespace Tests\Feature\Mailing;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Mailing\Contracts\EmailIdentityGatewayContract;
use App\Modules\Mailing\Mail\SendingDomainTestMail;
use App\Modules\Mailing\Models\SendingDomain;
use App\Modules\Mailing\Services\Identity\FakeEmailIdentityGateway;
use App\Modules\Signatures\Mail\DocumentReadyMail;
use App\Modules\Signatures\Models\Document;
use App\Modules\Signatures\Models\DocumentSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class SendingDomainTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private FakeEmailIdentityGateway $ses;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.from.address' => 'nao-responda@moduhub.com.br', 'mail.from.name' => 'Moduhub']);
        $this->ses = app(EmailIdentityGatewayContract::class);
        [$this->tenant, $this->owner] = $this->makeTenant('Empresa X');
    }

    public function test_owner_registers_the_domain_and_gets_the_dns_records(): void
    {
        $this->ses->holdPending('empresax.com.br');

        $this->asOwner()->post(route('sending-domain.store'), [
            'domain' => 'https://www.EmpresaX.com.br/',
            'from_name' => 'Empresa X',
            'from_local_part' => 'Contato@empresax.com.br',
            'reply_to' => 'comercial@empresax.com.br',
        ])->assertRedirect(route('sending-domain.show'));

        $domain = SendingDomain::withoutTenantScope()->sole();
        $this->assertSame('empresax.com.br', $domain->domain);
        $this->assertSame('contato', $domain->from_local_part);
        $this->assertSame($this->tenant->getKey(), $domain->tenant_id);
        $this->assertSame(SendingDomain::STATUS_PENDING, $domain->status);
        $this->assertArrayHasKey('empresax.com.br', $this->ses->identities);

        $this->asOwner()->get(route('sending-domain.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mailing/SendingDomain')
                ->where('domain.status', 'pending')
                ->has('domain.records', 6)
                ->where('domain.records.0.type', 'CNAME')
                ->where('domain.records.3.name', 'bounce.empresax.com.br')
                ->where('domain.records.5.name', '_dmarc.empresax.com.br'));
    }

    public function test_emails_use_the_tenant_sender_only_after_verification(): void
    {
        $this->ses->holdPending('empresax.com.br');
        $this->register();

        $this->assertNull($this->signatureEnvelope()->from);

        $this->ses = new FakeEmailIdentityGateway;
        $this->app->instance(EmailIdentityGatewayContract::class, $this->ses);
        $this->asOwner()->post(route('sending-domain.verify'))->assertSessionHas('success');

        $envelope = $this->signatureEnvelope();
        $this->assertSame('contato@empresax.com.br', $envelope->from->address);
        $this->assertSame('Empresa X', $envelope->from->name);
        $this->assertSame('comercial@empresax.com.br', $envelope->replyTo[0]->address);
    }

    public function test_domain_that_loses_its_dns_falls_back_to_the_platform_sender(): void
    {
        $this->register();
        $this->asOwner()->post(route('sending-domain.verify'));
        $this->assertNotNull($this->signatureEnvelope()->from);

        $this->ses->fail('empresax.com.br');
        $this->artisan('mailing:check-domains')->assertSuccessful();

        $this->assertSame(SendingDomain::STATUS_FAILED, SendingDomain::withoutTenantScope()->sole()->status);
        $this->assertNull($this->signatureEnvelope()->from);
    }

    public function test_mailbox_providers_and_the_platform_domain_are_refused(): void
    {
        foreach (['gmail.com', 'hotmail.com', 'moduhub.com.br', 'mail.moduhub.com.br', 'nao é domínio'] as $domain) {
            $this->asOwner()->post(route('sending-domain.store'), $this->payload(['domain' => $domain]))
                ->assertSessionHasErrors('domain');
        }

        $this->assertSame(0, SendingDomain::withoutTenantScope()->count());
    }

    public function test_a_domain_belongs_to_one_workspace_and_a_workspace_has_one_domain(): void
    {
        $this->register();

        $this->asOwner()->post(route('sending-domain.store'), $this->payload(['domain' => 'outro.com.br']))
            ->assertSessionHasErrors(['domain' => 'Este workspace já tem um domínio de envio. Remova o atual para cadastrar outro.']);

        [$other, $otherOwner] = $this->makeTenant('Concorrente');
        $this->actingAs($otherOwner)->withSession(['current_tenant_id' => $other->getKey()])
            ->post(route('sending-domain.store'), $this->payload())
            ->assertSessionHasErrors(['domain' => 'Este domínio já está cadastrado em outro workspace.']);
    }

    public function test_members_cannot_manage_the_domain(): void
    {
        $member = User::factory()->create();
        $this->tenant->users()->attach($member, ['role' => 'member']);
        $session = ['current_tenant_id' => $this->tenant->getKey()];

        $this->actingAs($member)->withSession($session)->get(route('sending-domain.show'))->assertForbidden();
        $this->actingAs($member)->withSession($session)->post(route('sending-domain.store'), $this->payload())->assertForbidden();
    }

    public function test_sender_can_be_changed_and_domain_removed(): void
    {
        $this->register();

        $this->asOwner()->patch(route('sending-domain.update'), [
            'from_name' => 'Financeiro X',
            'from_local_part' => 'financeiro',
            'reply_to' => '',
        ])->assertSessionHasNoErrors();

        $domain = SendingDomain::withoutTenantScope()->sole();
        $this->assertSame('financeiro@empresax.com.br', $domain->fromAddress());
        $this->assertNull($domain->reply_to);

        $this->asOwner()->delete(route('sending-domain.destroy'))->assertRedirect(route('sending-domain.show'));

        $this->assertSame(0, SendingDomain::withoutTenantScope()->count());
        $this->assertArrayNotHasKey('empresax.com.br', $this->ses->identities);
    }

    public function test_test_email_goes_to_the_user_with_the_current_sender(): void
    {
        Mail::fake();

        $this->asOwner()->post(route('sending-domain.test'), ['to' => $this->owner->email])
            ->assertSessionHas('success', "E-mail de teste enviado para {$this->owner->email}, com o remetente nao-responda@moduhub.com.br.");

        $this->register();
        $this->asOwner()->post(route('sending-domain.verify'));
        $this->asOwner()->post(route('sending-domain.test'), ['to' => ' financeiro@cliente.com.br '])
            ->assertSessionHas('success', 'E-mail de teste enviado para financeiro@cliente.com.br, com o remetente contato@empresax.com.br.');

        $this->asOwner()->post(route('sending-domain.test'), ['to' => 'nao-e-email'])->assertSessionHasErrors('to');

        Mail::assertSent(SendingDomainTestMail::class, 2);
        Mail::assertSent(SendingDomainTestMail::class, fn ($mail) => $mail->hasTo('financeiro@cliente.com.br')
            && $mail->envelope()->from?->address === 'contato@empresax.com.br');
    }

    public function test_test_email_refused_by_ses_shows_a_readable_error(): void
    {
        Mail::shouldReceive('to')->andThrow(new TransportException('Email address is not verified. The following identities failed the check'));

        $this->asOwner()->post(route('sending-domain.test'), ['to' => 'fulano@example.com'])
            ->assertSessionHas('error', 'O envio de e-mails ainda está em modo de teste na Moduhub e só entrega para endereços liberados. Fale com o suporte.');
    }

    public function test_members_cannot_send_test_emails(): void
    {
        $member = User::factory()->create();
        $this->tenant->users()->attach($member, ['role' => 'member']);

        $this->actingAs($member)->withSession(['current_tenant_id' => $this->tenant->getKey()])
            ->post(route('sending-domain.test'), ['to' => 'fulano@example.com'])
            ->assertForbidden();
    }

    private function register(): void
    {
        $this->asOwner()->post(route('sending-domain.store'), $this->payload())->assertSessionHasNoErrors();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'domain' => 'empresax.com.br',
            'from_name' => 'Empresa X',
            'from_local_part' => 'contato',
            'reply_to' => 'comercial@empresax.com.br',
        ], $overrides);
    }

    private function signatureEnvelope()
    {
        $signer = new DocumentSigner(['email' => 'fulano@example.com', 'name' => 'Fulano']);
        $document = new Document(['name' => 'Contrato']);

        return (new DocumentReadyMail($signer, $document, $this->tenant->fresh()))->envelope();
    }

    private function asOwner(): static
    {
        return $this->actingAs($this->owner)->withSession(['current_tenant_id' => $this->tenant->getKey()]);
    }

    /** @return array{0: Tenant, 1: User} */
    private function makeTenant(string $name): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => $name, 'slug' => Str::slug($name).'-'.Str::random(4), 'status' => 'active']);
        $tenant->users()->attach($user, ['role' => 'owner']);

        return [$tenant, $user];
    }
}
