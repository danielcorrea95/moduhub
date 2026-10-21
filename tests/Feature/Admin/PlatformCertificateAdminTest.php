<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Invoices\Services\CertificateKeyRotationService;
use App\Modules\Invoices\Services\Nfse\IssuerCredentialsLoader;
use App\Modules\Signatures\Mail\PlatformCertificateAlertMail;
use App\Modules\Signatures\Models\PlatformCertificate;
use App\Modules\Signatures\Services\PlatformSealService;
use App\Platform\Security\CertificateEncrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesPkcs12;
use Tests\TestCase;

class PlatformCertificateAdminTest extends TestCase
{
    use CreatesPkcs12, RefreshDatabase;

    private const PLATFORM_CNPJ = '12345678000190';

    private User $admin;

    private string $rootsPath;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        // Cada payload() gera uma hierarquia nova e grava a raiz dela aqui.
        $this->rootsPath = tempnam(sys_get_temp_dir(), 'mdhb_roots_');
        config([
            'signatures.icp.roots_path' => $this->rootsPath,
            'signatures.icp.intermediates_path' => '',
            'signatures.seal.platform_cnpj' => '12.345.678/0001-90',
            'signatures.seal.certificate_password' => null,
            'signatures.seal.alert_emails' => ['tecnico@moduhub.test'],
        ]);

        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_platform_admin' => true])->save();
    }

    protected function tearDown(): void
    {
        @unlink($this->rootsPath);

        parent::tearDown();
    }

    public function test_only_platform_admins_reach_the_certificate_page(): void
    {
        $this->get(route('admin.platform-certificate.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.platform-certificate.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->post(route('admin.platform-certificate.store'), $this->payload('MODUHUB:'.self::PLATFORM_CNPJ))
            ->assertForbidden();

        $this->assertSame(0, PlatformCertificate::count());
    }

    public function test_admin_installs_the_certificate_and_the_seal_and_adn_use_it(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.platform-certificate.store'), $this->payload('MODUHUB LTDA:'.self::PLATFORM_CNPJ))
            ->assertRedirect(route('admin.platform-certificate.index'))
            ->assertSessionHasNoErrors();

        $certificate = PlatformCertificate::sole();
        $this->assertTrue($certificate->isCurrent());
        $this->assertSame(self::PLATFORM_CNPJ, $certificate->cnpj);
        $this->assertSame($this->admin->name, $certificate->uploaded_by_label);

        // No banco o .pfx e a senha só existem cifrados.
        $raw = DB::table('platform_certificates')->first();
        $this->assertNotSame(self::PKCS12_PASSWORD, $raw->password);
        $this->assertSame(self::PKCS12_PASSWORD, app(CertificateEncrypter::class)->decrypt($raw->password));

        $seal = app(PlatformSealService::class);
        $this->assertTrue($seal->isConfigured());
        $this->assertSame('MODUHUB LTDA:'.self::PLATFORM_CNPJ, $seal->certificate()->subjectCn);
        $this->assertNotNull(app(IssuerCredentialsLoader::class)->platform());

        Mail::assertSent(PlatformCertificateAlertMail::class, fn (PlatformCertificateAlertMail $mail) => $mail->hasTo('tecnico@moduhub.test')
            && str_contains($mail->alert, $this->admin->name)
            && str_contains($mail->alert, $certificate->fingerprint));
    }

    public function test_the_page_never_exposes_the_file_or_the_password(): void
    {
        $this->actingAs($this->admin)->post(route('admin.platform-certificate.store'), $this->payload('MODUHUB:'.self::PLATFORM_CNPJ));

        $response = $this->actingAs($this->admin)->get(route('admin.platform-certificate.index'))->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/PlatformCertificate')
            ->where('current.cnpj', self::PLATFORM_CNPJ)
            ->missing('current.pfx')
            ->missing('current.password')
            ->has('history', 1));
        $this->assertStringNotContainsString(self::PKCS12_PASSWORD, $response->getContent());
    }

    public function test_certificate_of_another_company_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.platform-certificate.store'), $this->payload('OUTRA EMPRESA:98765432000110'))
            ->assertSessionHasErrors('certificate');

        $this->assertSame(0, PlatformCertificate::count());
        Mail::assertNothingSent();
    }

    public function test_self_signed_certificate_with_the_platform_cnpj_is_refused(): void
    {
        // O ataque que a cadeia barra: alguém gera um certificado próprio com o CNPJ da Moduhub no nome.
        $this->actingAs($this->admin)
            ->post(route('admin.platform-certificate.store'), [
                ...$this->payload('MODUHUB:'.self::PLATFORM_CNPJ),
                'certificate' => UploadedFile::fake()->createWithContent('falso.pfx', $this->makePkcs12('MODUHUB:'.self::PLATFORM_CNPJ)),
            ])
            ->assertSessionHasErrors('certificate');

        $this->assertSame(0, PlatformCertificate::count());
        Mail::assertNothingSent();
    }

    public function test_certificate_from_another_hierarchy_is_refused(): void
    {
        $foreign = $this->makeTrustedPkcs12('MODUHUB:'.self::PLATFORM_CNPJ);
        $this->payload('MODUHUB:'.self::PLATFORM_CNPJ);

        $this->actingAs($this->admin)
            ->post(route('admin.platform-certificate.store'), [
                'certificate' => UploadedFile::fake()->createWithContent('moduhub.pfx', $foreign['pfx']),
                'password' => self::PKCS12_PASSWORD,
                'current_password' => 'password',
            ])
            ->assertSessionHasErrors('certificate');

        $this->assertSame(0, PlatformCertificate::count());
    }

    public function test_pfx_exported_without_the_chain_uses_the_bundled_intermediates(): void
    {
        $hierarchy = $this->makeTrustedPkcs12('MODUHUB:'.self::PLATFORM_CNPJ, includeChain: false);
        file_put_contents($this->rootsPath, $hierarchy['root']);
        $payload = [
            'certificate' => UploadedFile::fake()->createWithContent('moduhub.pfx', $hierarchy['pfx']),
            'password' => self::PKCS12_PASSWORD,
            'current_password' => 'password',
        ];

        $this->actingAs($this->admin)->post(route('admin.platform-certificate.store'), $payload)->assertSessionHasErrors('certificate');

        $bundle = tempnam(sys_get_temp_dir(), 'mdhb_intermediates_');
        file_put_contents($bundle, $hierarchy['intermediate']);
        config(['signatures.icp.intermediates_path' => $bundle]);

        try {
            $payload['certificate'] = UploadedFile::fake()->createWithContent('moduhub.pfx', $hierarchy['pfx']);
            $this->actingAs($this->admin)->post(route('admin.platform-certificate.store'), $payload)->assertSessionHasNoErrors();
        } finally {
            @unlink($bundle);
        }

        $this->assertSame(1, PlatformCertificate::count());
    }

    public function test_bundled_icp_brasil_chain_closes_at_the_iti_roots(): void
    {
        $roots = resource_path('certificates/icp-brasil/roots.pem');
        $intermediates = resource_path('certificates/icp-brasil/intermediates.pem');

        $this->assertSame(config('signatures.icp.roots_path'), $this->rootsPath, 'O teste usa a raiz própria; a de produção é conferida abaixo.');
        $this->assertGreaterThanOrEqual(7, substr_count((string) file_get_contents($roots), 'BEGIN CERTIFICATE'));

        preg_match_all('/-----BEGIN CERTIFICATE-----.+?-----END CERTIFICATE-----/s', (string) file_get_contents($intermediates), $pems);
        $this->assertNotEmpty($pems[0]);

        foreach ($pems[0] as $pem) {
            $this->assertTrue(
                openssl_x509_checkpurpose($pem, X509_PURPOSE_ANY, [$roots], $intermediates),
                'AC fora da cadeia do ITI: '.(openssl_x509_parse($pem)['subject']['CN'] ?? '?'),
            );
        }
    }

    public function test_branch_certificate_of_the_platform_is_accepted(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.platform-certificate.store'), $this->payload('MODUHUB FILIAL:12345678000270'))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, PlatformCertificate::count());
    }

    public function test_upload_requires_the_account_password(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.platform-certificate.store'), [
                ...$this->payload('MODUHUB:'.self::PLATFORM_CNPJ),
                'current_password' => 'errada',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertSame(0, PlatformCertificate::count());
    }

    public function test_upload_is_refused_without_the_platform_cnpj_configured(): void
    {
        config(['signatures.seal.platform_cnpj' => null]);

        $this->actingAs($this->admin)
            ->post(route('admin.platform-certificate.store'), $this->payload('MODUHUB:'.self::PLATFORM_CNPJ))
            ->assertSessionHasErrors('certificate');

        $this->assertSame(0, PlatformCertificate::count());
    }

    public function test_wrong_certificate_password_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.platform-certificate.store'), [
                ...$this->payload('MODUHUB:'.self::PLATFORM_CNPJ),
                'password' => 'senha-errada',
            ])
            ->assertSessionHasErrors('password');

        $this->assertSame(0, PlatformCertificate::count());
    }

    public function test_replacing_retires_the_old_one_and_erases_its_key(): void
    {
        $this->actingAs($this->admin)->post(route('admin.platform-certificate.store'), $this->payload('MODUHUB 2026:'.self::PLATFORM_CNPJ));
        $this->actingAs($this->admin)->post(route('admin.platform-certificate.store'), $this->payload('MODUHUB 2027:'.self::PLATFORM_CNPJ));

        $old = PlatformCertificate::where('subject', 'MODUHUB 2026:'.self::PLATFORM_CNPJ)->sole();
        $this->assertFalse($old->isCurrent());
        $this->assertSame(PlatformCertificate::RETIRED_REPLACED, $old->retired_reason);
        $this->assertNull($old->pfx);
        $this->assertNull($old->password);

        $this->assertSame('MODUHUB 2027:'.self::PLATFORM_CNPJ, app(PlatformSealService::class)->certificate()->subjectCn);
        Mail::assertSent(PlatformCertificateAlertMail::class, 2);
    }

    public function test_removing_falls_back_to_the_server_certificate(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mdhb_seal_');
        file_put_contents($path, $this->makePkcs12('MODUHUB SERVIDOR:'.self::PLATFORM_CNPJ));
        config(['signatures.seal.certificate_path' => $path, 'signatures.seal.certificate_password' => self::PKCS12_PASSWORD]);

        try {
            $this->actingAs($this->admin)->post(route('admin.platform-certificate.store'), $this->payload('MODUHUB PAINEL:'.self::PLATFORM_CNPJ));
            $this->assertSame('MODUHUB PAINEL:'.self::PLATFORM_CNPJ, app(PlatformSealService::class)->certificate()->subjectCn);

            $this->actingAs($this->admin)
                ->delete(route('admin.platform-certificate.destroy'), ['current_password' => 'password'])
                ->assertSessionHasNoErrors();

            $removed = PlatformCertificate::sole();
            $this->assertSame(PlatformCertificate::RETIRED_REMOVED, $removed->retired_reason);
            $this->assertNull($removed->pfx);
            $this->assertSame('MODUHUB SERVIDOR:'.self::PLATFORM_CNPJ, app(PlatformSealService::class)->certificate()->subjectCn);
            Mail::assertSent(PlatformCertificateAlertMail::class, fn (PlatformCertificateAlertMail $mail) => str_contains($mail->alert, 'removido'));
        } finally {
            @unlink($path);
        }
    }

    public function test_removing_requires_the_account_password(): void
    {
        $this->actingAs($this->admin)->post(route('admin.platform-certificate.store'), $this->payload('MODUHUB:'.self::PLATFORM_CNPJ));

        $this->actingAs($this->admin)
            ->delete(route('admin.platform-certificate.destroy'), ['current_password' => 'errada'])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(PlatformCertificate::sole()->isCurrent());
    }

    public function test_key_rotation_reencrypts_the_platform_certificate(): void
    {
        $this->actingAs($this->admin)->post(route('admin.platform-certificate.store'), $this->payload('MODUHUB:'.self::PLATFORM_CNPJ));
        $before = DB::table('platform_certificates')->value('pfx');

        $result = app(CertificateKeyRotationService::class)->reencryptAll();

        $this->assertSame(1, $result['reencrypted']);
        $this->assertSame([], $result['failed']);
        $this->assertNotSame($before, DB::table('platform_certificates')->value('pfx'));
        $this->assertSame('MODUHUB:'.self::PLATFORM_CNPJ, app(PlatformSealService::class)->certificate()->subjectCn);
    }

    /** @return array<string, mixed> */
    private function payload(string $commonName): array
    {
        $hierarchy = $this->makeTrustedPkcs12($commonName);
        file_put_contents($this->rootsPath, $hierarchy['root']);

        return [
            'certificate' => UploadedFile::fake()->createWithContent('moduhub.pfx', $hierarchy['pfx']),
            'password' => self::PKCS12_PASSWORD,
            // Senha padrão da UserFactory.
            'current_password' => 'password',
        ];
    }
}
