<?php

namespace Tests\Feature\Mailing;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Mailing\Contracts\EmailIdentityGatewayContract;
use App\Modules\Mailing\Models\SmtpCredential;
use App\Modules\Mailing\Services\Identity\FakeEmailIdentityGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SmtpCredentialTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.from.address' => 'nao-responda@moduhub.com.br', 'mailing.relay.host' => 'smtp.moduhub.com.br']);
        [$this->tenant, $this->owner] = $this->makeTenant('Empresa X');
    }

    public function test_credentials_require_a_verified_domain(): void
    {
        $this->asOwner()->post(route('smtp-credentials.store'), ['name' => 'Site'])
            ->assertSessionHasErrors(['name' => 'Verifique o domínio de envio antes de criar credenciais SMTP.']);

        /** @var FakeEmailIdentityGateway $ses */
        $ses = app(EmailIdentityGatewayContract::class);
        $ses->holdPending('empresax.com.br');
        $this->registerDomain(verify: false);

        $this->asOwner()->post(route('smtp-credentials.store'), ['name' => 'Site'])->assertSessionHasErrors('name');
        $this->assertSame(0, SmtpCredential::withoutTenantScope()->count());
    }

    public function test_password_is_shown_once_and_only_its_hash_is_kept(): void
    {
        $this->registerDomain();

        $this->asOwner()->post(route('smtp-credentials.store'), ['name' => ' ERP '])
            ->assertRedirect(route('sending-domain.show', ['aba' => 'smtp']));

        $credential = SmtpCredential::withoutTenantScope()->sole();
        $this->assertSame('ERP', $credential->name);
        $this->assertStringStartsWith('mdhb_smtp_', $credential->username);
        $this->assertSame($this->tenant->getKey(), $credential->tenant_id);

        $page = $this->asOwner()->get(route('sending-domain.show'));
        $page->assertInertia(fn (Assert $p) => $p
            ->where('smtp.host', 'smtp.moduhub.com.br')
            ->where('smtp.enabled', false)
            ->where('smtp.reveal.username', $credential->username)
            ->has('smtp.credentials', 1)
            ->missing('smtp.credentials.0.password_hash'));

        $password = $page->viewData('page')['props']['smtp']['reveal']['password'];
        $this->assertSame(40, strlen($password));
        $this->assertSame(hash('sha256', $password), $credential->password_hash);
        $this->assertSame(substr($password, -4), $credential->last_four);

        $this->asOwner()->get(route('sending-domain.show'))
            ->assertInertia(fn (Assert $p) => $p->where('smtp.reveal', null));
    }

    public function test_revoke_and_isolation_between_workspaces(): void
    {
        $this->registerDomain();
        $this->asOwner()->post(route('smtp-credentials.store'), ['name' => 'Site']);
        $credential = SmtpCredential::withoutTenantScope()->sole();

        [$other, $otherOwner] = $this->makeTenant('Concorrente');
        $this->actingAs($otherOwner)->withSession(['current_tenant_id' => $other->getKey()])
            ->delete(route('smtp-credentials.destroy', $credential))
            ->assertNotFound();

        $this->asOwner()->delete(route('smtp-credentials.destroy', $credential))->assertRedirect(route('sending-domain.show', ['aba' => 'smtp']));
        $this->assertSame(0, SmtpCredential::withoutTenantScope()->count());
    }

    public function test_limit_and_member_access(): void
    {
        $this->registerDomain();

        for ($i = 1; $i <= SmtpCredential::MAX_PER_TENANT; $i++) {
            $this->asOwner()->post(route('smtp-credentials.store'), ['name' => "Sistema {$i}"])->assertSessionHasNoErrors();
        }
        $this->asOwner()->post(route('smtp-credentials.store'), ['name' => 'Mais um'])->assertSessionHasErrors('name');

        $member = User::factory()->create();
        $this->tenant->users()->attach($member, ['role' => 'member']);
        $this->actingAs($member)->withSession(['current_tenant_id' => $this->tenant->getKey()])
            ->post(route('smtp-credentials.store'), ['name' => 'Outro'])
            ->assertForbidden();
    }

    private function registerDomain(bool $verify = true): void
    {
        $this->asOwner()->post(route('sending-domain.store'), [
            'domain' => 'empresax.com.br',
            'from_name' => 'Empresa X',
            'from_local_part' => 'contato',
        ])->assertSessionHasNoErrors();

        if ($verify) {
            $this->asOwner()->post(route('sending-domain.verify'));
        }
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
