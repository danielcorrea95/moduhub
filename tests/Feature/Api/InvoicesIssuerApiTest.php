<?php

namespace Tests\Feature\Api;

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Invoices\Models\Issuer;
use App\Platform\Contracts\ApiKeyServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CreatesPkcs12;
use Tests\TestCase;

class InvoicesIssuerApiTest extends TestCase
{
    use CreatesPkcs12, RefreshDatabase;

    private const CNPJ = '12345678000195';

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->owner] = $this->makeTenant('Acme');
    }

    public function test_returns_404_until_an_issuer_exists(): void
    {
        $this->withToken($this->key(['invoices:read']))
            ->getJson('/api/v1/invoices/issuer')
            ->assertNotFound();
    }

    public function test_read_only_key_cannot_configure(): void
    {
        $this->withToken($this->key(['invoices:read']))
            ->putJson('/api/v1/invoices/issuer', $this->issuerPayload())
            ->assertForbidden()
            ->assertJsonPath('required_scope', 'invoices:write');
    }

    public function test_key_without_invoices_scope_is_rejected_by_the_module(): void
    {
        $this->withToken($this->key(['signatures:*']))
            ->getJson('/api/v1/invoices/issuer')
            ->assertForbidden()
            ->assertJsonPath('module', 'invoices');
    }

    public function test_creates_then_updates_the_issuer(): void
    {
        $key = $this->key(['invoices:*']);

        $this->withToken($key)
            ->putJson('/api/v1/invoices/issuer', $this->issuerPayload(['cnpj' => '12.345.678/0001-95']))
            ->assertCreated()
            ->assertJsonPath('data.cnpj', self::CNPJ)
            ->assertJsonPath('data.simples_regime', Issuer::SIMPLES_REGIME_ALL_IN_SN)
            ->assertJsonPath('data.ready_to_issue', false)
            ->assertJsonPath('data.certificate', null)
            ->assertJsonMissingPath('data.certificate_pfx');

        $this->withToken($key)
            ->putJson('/api/v1/invoices/issuer', $this->issuerPayload(['legal_name' => 'Acme Serviços Ltda', 'environment' => 'production']))
            ->assertOk()
            ->assertJsonPath('data.legal_name', 'Acme Serviços Ltda')
            ->assertJsonPath('data.environment', 'production');

        $this->assertSame(1, Issuer::withoutGlobalScopes()->count());

        $this->withToken($this->key(['invoices:read']))
            ->getJson('/api/v1/invoices/issuer')
            ->assertOk()
            ->assertJsonPath('data.legal_name', 'Acme Serviços Ltda');
    }

    public function test_validation_errors_come_back_as_422(): void
    {
        $this->withToken($this->key(['invoices:write']))
            ->putJson('/api/v1/invoices/issuer', $this->issuerPayload(['cnpj' => '00000000000000', 'environment' => 'staging']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cnpj', 'environment']);
    }

    public function test_uploads_certificate_in_base64_and_removes_it(): void
    {
        $key = $this->key(['invoices:*']);
        $this->withToken($key)->putJson('/api/v1/invoices/issuer', $this->issuerPayload())->assertCreated();

        $this->withToken($key)
            ->postJson('/api/v1/invoices/issuer/certificate', [
                'certificate_base64' => base64_encode($this->makePkcs12('ACME LTDA:'.self::CNPJ)),
                'password' => self::PKCS12_PASSWORD,
            ])
            ->assertOk()
            ->assertJsonPath('data.ready_to_issue', true)
            ->assertJsonPath('data.certificate.cnpj', self::CNPJ)
            ->assertJsonPath('data.certificate.expired', false)
            ->assertJsonMissingPath('data.certificate.password');

        $this->withToken($key)
            ->deleteJson('/api/v1/invoices/issuer/certificate')
            ->assertNoContent();

        $this->assertFalse(Issuer::withoutGlobalScopes()->firstOrFail()->hasCertificate());
    }

    public function test_rejects_certificate_of_another_company_with_422(): void
    {
        $key = $this->key(['invoices:*']);
        $this->withToken($key)->putJson('/api/v1/invoices/issuer', $this->issuerPayload())->assertCreated();

        $this->withToken($key)
            ->postJson('/api/v1/invoices/issuer/certificate', [
                'certificate_base64' => base64_encode($this->makePkcs12('OUTRA LTDA:11222333000181')),
                'password' => self::PKCS12_PASSWORD,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('certificate');
    }

    public function test_rejects_invalid_base64(): void
    {
        $key = $this->key(['invoices:*']);
        $this->withToken($key)->putJson('/api/v1/invoices/issuer', $this->issuerPayload())->assertCreated();

        $this->withToken($key)
            ->postJson('/api/v1/invoices/issuer/certificate', ['certificate_base64' => '%%%', 'password' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('certificate_base64');
    }

    public function test_cannot_see_the_issuer_of_another_tenant(): void
    {
        $this->withToken($this->key(['invoices:*']))->putJson('/api/v1/invoices/issuer', $this->issuerPayload())->assertCreated();

        [$otherTenant, $otherOwner] = $this->makeTenant('Outra');
        $otherKey = app(ApiKeyServiceContract::class)->generate($otherTenant, $otherOwner, 'Teste', ['*'])['plaintext'];

        $this->withToken($otherKey)->getJson('/api/v1/invoices/issuer')->assertNotFound();
    }

    public function test_module_must_be_active_for_the_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Sem módulo', 'slug' => 'sem-modulo-'.Str::random(4), 'status' => 'active']);
        $user = User::factory()->create();
        $tenant->users()->attach($user, ['role' => 'owner']);
        $key = app(ApiKeyServiceContract::class)->generate($tenant, $user, 'Teste', ['*'])['plaintext'];

        $this->withToken($key)->getJson('/api/v1/invoices/issuer')->assertStatus(402);
    }

    /** @return array{0: Tenant, 1: User} */
    private function makeTenant(string $name): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => $name, 'slug' => Str::slug($name).'-'.Str::random(4), 'status' => 'active']);
        $tenant->users()->attach($user, ['role' => 'owner']);
        $module = Module::firstOrCreate(['key' => Module::INVOICES], ['name' => 'Notas Fiscais']);
        $tenant->modules()->attach($module, ['source' => 'manual', 'is_active' => true, 'activated_at' => now()]);

        return [$tenant, $user];
    }

    private function key(array $scopes): string
    {
        return app(ApiKeyServiceContract::class)
            ->generate($this->tenant, $this->owner, 'Teste', $scopes)['plaintext'];
    }

    private function issuerPayload(array $overrides = []): array
    {
        return array_merge([
            'legal_name' => 'Acme Ltda',
            'trade_name' => 'Acme',
            'cnpj' => self::CNPJ,
            'municipal_registration' => '123456',
            'city_code' => '4313409',
            'city_name' => 'Novo Hamburgo',
            'state' => 'RS',
            'simples_nacional' => Issuer::SIMPLES_ME_EPP,
            'simples_regime' => Issuer::SIMPLES_REGIME_ALL_IN_SN,
            'special_regime' => Issuer::SPECIAL_REGIME_NONE,
            'email' => 'fiscal@example.com',
            'environment' => Issuer::ENV_RESTRICTED,
            'dps_series' => '1',
        ], $overrides);
    }
}
