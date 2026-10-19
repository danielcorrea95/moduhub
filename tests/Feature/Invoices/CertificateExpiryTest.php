<?php

namespace Tests\Feature\Invoices;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Modules\Invoices\Mail\CertificateExpiringMail;
use App\Modules\Invoices\Models\CertificateEvent;
use App\Modules\Invoices\Models\Issuer;
use App\Modules\Signatures\Mail\PlatformCertificateAlertMail;
use App\Platform\Contracts\WebhookDispatcherContract;
use App\Platform\Security\CertificateExpiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\Support\InteractsWithInvoices;
use Tests\TestCase;

class CertificateExpiryTest extends TestCase
{
    use InteractsWithInvoices, RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        [$this->tenant, $this->owner] = $this->makeInvoicesTenant();
        $this->configureIssuer($this->tenant);
    }

    public function test_milestones_are_computed_from_the_days_left(): void
    {
        $this->assertNull(CertificateExpiry::noticeFor(31));
        $this->assertSame(30, CertificateExpiry::noticeFor(30));
        $this->assertSame(15, CertificateExpiry::noticeFor(10));
        $this->assertSame(7, CertificateExpiry::noticeFor(7));
        $this->assertSame(1, CertificateExpiry::noticeFor(1));
        $this->assertSame(0, CertificateExpiry::noticeFor(0));

        $this->assertSame(1, CertificateExpiry::daysLeft(now()->addHours(3)), 'Faltando horas ainda é 1 dia, não vencido.');
        $this->assertSame(0, CertificateExpiry::daysLeft(now()->subMinute()));
    }

    public function test_nothing_is_sent_while_more_than_thirty_days_remain(): void
    {
        $this->artisan('invoices:check-certificates')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_managers_and_the_fiscal_contact_are_warned_once_per_milestone(): void
    {
        $admin = $this->member(TenantUser::ROLE_ADMIN);
        $member = $this->member(TenantUser::ROLE_MEMBER);
        $this->travelTo($this->issuer()->certificate_valid_until->copy()->subDays(20));

        $this->artisan('invoices:check-certificates')->assertSuccessful();
        $this->artisan('invoices:check-certificates')->assertSuccessful();

        foreach ([$this->owner->email, $admin->email, 'fiscal@acme.test'] as $email) {
            Mail::assertSent(CertificateExpiringMail::class, fn (CertificateExpiringMail $mail) => $mail->hasTo($email) && $mail->daysLeft === 20);
        }
        Mail::assertNotSent(CertificateExpiringMail::class, fn (CertificateExpiringMail $mail) => $mail->hasTo($member->email));
        Mail::assertSentCount(3);

        $issuer = $this->issuer();
        $this->assertSame(30, $issuer->certificate_expiry_notice_days);
        $notice = CertificateEvent::query()->withoutTenantScope()->where('type', CertificateEvent::TYPE_EXPIRY_NOTICE)->sole();
        $this->assertSame(20, $notice->data['days_left']);
        $this->assertSame('system', $notice->actor_type);
    }

    public function test_each_milestone_and_the_expiry_get_their_own_notice(): void
    {
        $validUntil = $this->issuer()->certificate_valid_until->copy();

        foreach ([25 => 30, 12 => 15, 5 => 7, 1 => 1] as $daysBefore => $milestone) {
            // Uma hora depois do marco: faltam pouco menos de N dias, que arredonda para N.
            $this->travelTo($validUntil->copy()->subDays($daysBefore)->addHour());
            $this->artisan('invoices:check-certificates')->assertSuccessful();
            $this->assertSame($milestone, $this->issuer()->certificate_expiry_notice_days);
        }

        $this->travelTo($validUntil->copy()->addHour());
        $this->artisan('invoices:check-certificates')->assertSuccessful();

        Mail::assertSent(CertificateExpiringMail::class, fn (CertificateExpiringMail $mail) => $mail->daysLeft === 0
            && str_contains($mail->envelope()->subject, 'venceu'));
        $this->assertSame(5, CertificateEvent::query()->withoutTenantScope()->where('type', CertificateEvent::TYPE_EXPIRY_NOTICE)->count());
    }

    public function test_a_late_run_sends_only_the_current_milestone(): void
    {
        $this->travelTo($this->issuer()->certificate_valid_until->copy()->subDays(3));

        $this->artisan('invoices:check-certificates')->assertSuccessful();

        $this->assertSame(7, $this->issuer()->certificate_expiry_notice_days);
        $this->assertSame(1, CertificateEvent::query()->withoutTenantScope()->where('type', CertificateEvent::TYPE_EXPIRY_NOTICE)->count());
    }

    public function test_uploading_a_new_certificate_restarts_the_milestones(): void
    {
        $this->travelTo($this->issuer()->certificate_valid_until->copy()->subDays(5));
        $this->artisan('invoices:check-certificates')->assertSuccessful();
        $this->assertSame(7, $this->issuer()->certificate_expiry_notice_days);

        $this->configureIssuer($this->tenant);

        $this->assertNull($this->issuer()->certificate_expiry_notice_days);
    }

    public function test_integrations_receive_the_webhook(): void
    {
        $this->mock(WebhookDispatcherContract::class)
            ->shouldReceive('fire')
            ->once()
            ->with(Mockery::on(fn ($tenant) => $tenant->is($this->tenant)), 'invoice.certificate_expiring', Mockery::on(
                fn (array $payload) => $payload['days_left'] === 10 && $payload['expired'] === false && $payload['cnpj'] === self::ISSUER_CNPJ,
            ));
        $this->travelTo($this->issuer()->certificate_valid_until->copy()->subDays(10));

        $this->artisan('invoices:check-certificates')->assertSuccessful();
    }

    public function test_platform_certificate_close_to_expiry_alerts_the_operation_once(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mdhb_seal_');
        file_put_contents($path, $this->makePkcs12('MODUHUB TESTE LTDA:12345678000190'));
        config([
            'signatures.seal.certificate_path' => $path,
            'signatures.seal.certificate_password' => self::PKCS12_PASSWORD,
            'signatures.seal.alert_emails' => ['ops@moduhub.test'],
        ]);

        try {
            $this->travelTo(now()->addDays(355));
            $this->artisan('signatures:check-platform-certificate')->assertSuccessful();
            $this->artisan('signatures:check-platform-certificate')->assertSuccessful();

            Mail::assertSent(PlatformCertificateAlertMail::class, 1);
            Mail::assertSent(PlatformCertificateAlertMail::class, fn ($mail) => $mail->hasTo('ops@moduhub.test') && str_contains($mail->alert, 'vence em 10 dia'));
        } finally {
            @unlink($path);
        }
    }

    public function test_platform_certificate_that_does_not_open_alerts_every_run(): void
    {
        config([
            'signatures.seal.certificate_path' => '/nao/existe.pfx',
            'signatures.seal.certificate_password' => 'qualquer',
            'signatures.seal.alert_emails' => ['ops@moduhub.test'],
        ]);

        $this->artisan('signatures:check-platform-certificate')->assertSuccessful();
        $this->artisan('signatures:check-platform-certificate')->assertSuccessful();

        Mail::assertSent(PlatformCertificateAlertMail::class, fn ($mail) => str_contains($mail->alert, 'SEM o selo'));
        Mail::assertSent(PlatformCertificateAlertMail::class, 2);
    }

    private function issuer(): Issuer
    {
        return Issuer::query()->withoutTenantScope()->where('tenant_id', $this->tenant->getKey())->firstOrFail();
    }

    private function member(string $role): User
    {
        $user = User::factory()->create();
        $this->tenant->users()->attach($user, ['role' => $role]);

        return $user;
    }
}
