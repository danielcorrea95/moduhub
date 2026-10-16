<?php

namespace Tests\Feature\Invoices;

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Invoices\Models\Issuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesPkcs12;
use Tests\TestCase;

class IssuerSettingsTest extends TestCase
{
    use CreatesPkcs12, RefreshDatabase;

    private const CNPJ = '12345678000195';

    private const SUBJECT = 'ACME LTDA:'.self::CNPJ;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->tenant = $this->makeTenantWithInvoices('Acme');
        $this->tenant->users()->attach($this->owner, ['role' => 'owner']);
    }

    public function test_page_opens_without_an_issuer_and_tells_who_can_manage(): void
    {
        $this->actingAs($this->owner)
            ->get(route('invoices.issuer.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Invoices/Issuer')
                ->where('issuer', null)
                ->where('canManage', true)
                ->has('options.simples_nacional')
                ->has('options.environment'));
    }

    public function test_members_can_see_but_not_change_the_issuer(): void
    {
        $member = User::factory()->create();
        $this->tenant->users()->attach($member, ['role' => 'member']);

        $this->actingAs($member)
            ->get(route('invoices.issuer.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

        $this->actingAs($member)
            ->put(route('invoices.issuer.update'), $this->issuerPayload())
            ->assertForbidden();
    }

    public function test_owner_configures_the_issuer_with_normalized_data(): void
    {
        $this->actingAs($this->owner)
            ->put(route('invoices.issuer.update'), $this->issuerPayload([
                'cnpj' => '12.345.678/0001-95',
                'phone' => '(51) 3594-0000',
                'state' => 'rs',
                'simples_nacional' => Issuer::SIMPLES_NOT_OPTANT,
                'simples_regime' => Issuer::SIMPLES_REGIME_ALL_IN_SN,
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $issuer = Issuer::query()->withoutTenantScope()->firstOrFail();

        $this->assertSame($this->tenant->getKey(), $issuer->tenant_id);
        $this->assertSame(self::CNPJ, $issuer->cnpj);
        $this->assertSame('5135940000', $issuer->phone);
        $this->assertSame('RS', $issuer->state);
        // Fora do Simples o regime de apuração não existe, mesmo que o formulário mande um valor.
        $this->assertNull($issuer->simples_regime);
        $this->assertSame(Issuer::ENV_RESTRICTED, $issuer->environment);
    }

    public function test_saving_twice_updates_the_same_issuer(): void
    {
        $this->actingAs($this->owner)->put(route('invoices.issuer.update'), $this->issuerPayload());
        $this->actingAs($this->owner)->put(route('invoices.issuer.update'), $this->issuerPayload(['legal_name' => 'Acme Serviços Ltda']));

        $this->assertSame(1, Issuer::query()->withoutTenantScope()->count());
        $this->assertSame('Acme Serviços Ltda', Issuer::query()->withoutTenantScope()->value('legal_name'));
    }

    public function test_rejects_invalid_cnpj_city_code_and_missing_simples_regime(): void
    {
        $this->actingAs($this->owner)
            ->put(route('invoices.issuer.update'), $this->issuerPayload([
                'cnpj' => '12.345.678/0001-00',
                'city_code' => '431',
                'simples_nacional' => Issuer::SIMPLES_ME_EPP,
                'simples_regime' => null,
            ]))
            ->assertSessionHasErrors(['cnpj', 'city_code', 'simples_regime']);

        $this->assertSame(0, Issuer::query()->withoutTenantScope()->count());
    }

    public function test_accepts_alphanumeric_cnpj(): void
    {
        // 12.ABC.345/01DE-35 é um CNPJ alfanumérico com dígitos verificadores corretos.
        $this->actingAs($this->owner)
            ->put(route('invoices.issuer.update'), $this->issuerPayload(['cnpj' => '12.ABC.345/01DE-35']))
            ->assertSessionHasNoErrors();

        $this->assertSame('12ABC34501DE35', Issuer::query()->withoutTenantScope()->value('cnpj'));
    }

    public function test_certificate_requires_an_issuer_first(): void
    {
        $this->actingAs($this->owner)
            ->post(route('invoices.issuer.certificate.store'), [
                'certificate' => $this->pfxUpload($this->makePkcs12(self::SUBJECT)),
                'password' => self::PKCS12_PASSWORD,
            ])
            ->assertSessionHasErrors('certificate');
    }

    public function test_uploads_the_certificate_and_stores_it_encrypted(): void
    {
        $this->configureIssuer();
        $pfx = $this->makePkcs12(self::SUBJECT);

        $this->actingAs($this->owner)
            ->post(route('invoices.issuer.certificate.store'), [
                'certificate' => $this->pfxUpload($pfx),
                'password' => self::PKCS12_PASSWORD,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $issuer = Issuer::query()->withoutTenantScope()->firstOrFail();
        $this->assertTrue($issuer->isReadyToIssue());
        $this->assertSame(self::CNPJ, $issuer->certificate_cnpj);
        $this->assertSame(self::SUBJECT, $issuer->certificate_subject);
        $this->assertSame(64, strlen($issuer->certificate_fingerprint));
        $this->assertTrue($issuer->certificate_valid_until->isFuture());

        // O cast decifra para a aplicação, mas a coluna não pode conter o arquivo nem a senha às claras.
        $this->assertSame(base64_encode($pfx), $issuer->certificate_pfx);
        $raw = DB::table('invoice_issuers')->where('id', $issuer->getKey())->first();
        $this->assertStringNotContainsString(base64_encode($pfx), $raw->certificate_pfx);
        $this->assertStringNotContainsString(self::PKCS12_PASSWORD, $raw->certificate_password);

        $this->actingAs($this->owner)
            ->get(route('invoices.issuer.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('issuer.ready_to_issue', true)
                ->where('issuer.certificate.cnpj', self::CNPJ)
                ->where('issuer.certificate.expired', false)
                ->missing('issuer.certificate_pfx')
                ->missing('issuer.certificate_password'));
    }

    public function test_rejects_wrong_password(): void
    {
        $this->configureIssuer();

        $this->actingAs($this->owner)
            ->post(route('invoices.issuer.certificate.store'), [
                'certificate' => $this->pfxUpload($this->makePkcs12(self::SUBJECT)),
                'password' => 'errada',
            ])
            ->assertSessionHasErrors('password');

        $this->assertFalse(Issuer::query()->withoutTenantScope()->firstOrFail()->hasCertificate());
    }

    public function test_rejects_certificate_from_another_company(): void
    {
        $this->configureIssuer();

        $this->actingAs($this->owner)
            ->post(route('invoices.issuer.certificate.store'), [
                'certificate' => $this->pfxUpload($this->makePkcs12('OUTRA EMPRESA LTDA:11222333000181')),
                'password' => self::PKCS12_PASSWORD,
            ])
            ->assertSessionHasErrors('certificate');

        $this->assertStringContainsString('11.222.333/0001-81', session('errors')->first('certificate'));
    }

    public function test_accepts_certificate_of_a_branch_of_the_same_company(): void
    {
        $this->configureIssuer();

        $this->actingAs($this->owner)
            ->post(route('invoices.issuer.certificate.store'), [
                'certificate' => $this->pfxUpload($this->makePkcs12('ACME LTDA FILIAL:12345678000276')),
                'password' => self::PKCS12_PASSWORD,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('12345678000276', Issuer::query()->withoutTenantScope()->value('certificate_cnpj'));
    }

    public function test_rejects_e_cpf_and_ca_certificates(): void
    {
        $this->configureIssuer();

        $this->actingAs($this->owner)
            ->post(route('invoices.issuer.certificate.store'), [
                'certificate' => $this->pfxUpload($this->makePkcs12('JOAO DA SILVA:12345678901')),
                'password' => self::PKCS12_PASSWORD,
            ])
            ->assertSessionHasErrors('certificate');
        $this->assertStringContainsString('e-CNPJ', session('errors')->first('certificate'));

        $this->actingAs($this->owner)
            ->post(route('invoices.issuer.certificate.store'), [
                'certificate' => $this->pfxUpload($this->makePkcs12(self::SUBJECT, 'ca')),
                'password' => self::PKCS12_PASSWORD,
            ])
            ->assertSessionHasErrors('certificate');
        $this->assertStringContainsString('Autoridade Certificadora', session('errors')->first('certificate'));
    }

    public function test_removes_the_certificate(): void
    {
        $this->configureIssuer();
        $this->actingAs($this->owner)->post(route('invoices.issuer.certificate.store'), [
            'certificate' => $this->pfxUpload($this->makePkcs12(self::SUBJECT)),
            'password' => self::PKCS12_PASSWORD,
        ]);

        $this->actingAs($this->owner)
            ->delete(route('invoices.issuer.certificate.destroy'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $issuer = Issuer::query()->withoutTenantScope()->firstOrFail();
        $this->assertFalse($issuer->hasCertificate());
        $this->assertNull($issuer->certificate_pfx);
        $this->assertNull($issuer->certificate_cnpj);
    }

    public function test_module_must_be_active(): void
    {
        $tenant = Tenant::create(['name' => 'Sem módulo', 'slug' => 'sem-modulo-'.Str::random(4)]);
        $user = User::factory()->create();
        $tenant->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user)->get(route('invoices.issuer.edit'))->assertStatus(402);
    }

    private function configureIssuer(): void
    {
        $this->actingAs($this->owner)->put(route('invoices.issuer.update'), $this->issuerPayload())->assertSessionHasNoErrors();
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
            'phone' => '51999990000',
            'email' => 'fiscal@example.com',
            'environment' => Issuer::ENV_RESTRICTED,
            'dps_series' => '1',
        ], $overrides);
    }

    private function pfxUpload(string $pfx): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('certificado.pfx', $pfx);
    }

    private function makeTenantWithInvoices(string $name): Tenant
    {
        $tenant = Tenant::create(['name' => $name, 'slug' => Str::slug($name).'-'.Str::random(4)]);
        $module = Module::firstOrCreate(['key' => Module::INVOICES], ['name' => 'Notas Fiscais']);
        $tenant->modules()->attach($module, ['source' => 'manual', 'is_active' => true, 'activated_at' => now()]);

        return $tenant;
    }
}
