<?php

namespace Tests\Feature\Invoices;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Invoices\Models\CertificateEvent;
use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Services\Sefin\IssuerHttp;
use App\Platform\Audit\AuditActor;
use App\Platform\Contracts\ApiKeyServiceContract;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\InteractsWithInvoices;
use Tests\TestCase;

class CertificateAuditTest extends TestCase
{
    use InteractsWithInvoices, RefreshDatabase;

    private const SUBJECT = 'ACME LTDA:'.self::ISSUER_CNPJ;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->owner] = $this->makeInvoicesTenant();
        $this->configureIssuer($this->tenant, withCertificate: false);
    }

    public function test_upload_replacement_and_removal_are_recorded_with_who_did_it(): void
    {
        $this->upload();
        $firstFingerprint = $this->issuer()->certificate_fingerprint;
        $this->upload();
        $secondFingerprint = $this->issuer()->certificate_fingerprint;
        $this->actingAs($this->owner)->delete(route('invoices.issuer.certificate.destroy'))->assertRedirect();

        $events = $this->events();
        $this->assertSame([CertificateEvent::TYPE_UPLOADED, CertificateEvent::TYPE_REPLACED, CertificateEvent::TYPE_REMOVED], $events->pluck('type')->all());
        $this->assertSame([$firstFingerprint, $secondFingerprint, $secondFingerprint], $events->pluck('certificate_fingerprint')->all());
        $this->assertSame($firstFingerprint, $events[1]->data['previous_fingerprint']);

        foreach ($events as $event) {
            $this->assertSame(AuditActor::TYPE_USER, $event->actor_type);
            $this->assertSame($this->owner->getKey(), (int) $event->actor_id);
            $this->assertSame('127.0.0.1', $event->ip_address);
        }
    }

    public function test_upload_through_the_api_names_the_key(): void
    {
        $key = app(ApiKeyServiceContract::class)->generate($this->tenant, $this->owner, 'ERP da Acme', ['invoices:*'])['plaintext'];

        $this->withToken($key)->postJson('/api/v1/invoices/issuer/certificate', [
            'certificate_base64' => base64_encode($this->makePkcs12(self::SUBJECT)),
            'password' => self::PKCS12_PASSWORD,
        ])->assertOk();

        $event = $this->events()->sole();
        $this->assertSame(AuditActor::TYPE_API_KEY, $event->actor_type);
        $this->assertSame('API: ERP da Acme', $event->actor_label);
    }

    public function test_each_use_of_the_private_key_is_recorded_with_its_purpose(): void
    {
        $this->upload();
        $key = app(ApiKeyServiceContract::class)->generate($this->tenant, $this->owner, 'ERP da Acme', ['invoices:*'])['plaintext'];

        $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload(['external_id' => 'pedido-77']))->assertCreated();

        $used = $this->events()->where('type', CertificateEvent::TYPE_USED)->sole();
        $this->assertSame(CertificateEvent::PURPOSE_DPS_SIGNATURE, $used->purpose);
        $this->assertSame('pedido-77', $used->data['external_id']);
        $this->assertSame('API: ERP da Acme', $used->actor_label);
        $this->assertSame($this->issuer()->certificate_fingerprint, $used->certificate_fingerprint);
    }

    public function test_mtls_calls_from_background_jobs_are_recorded_as_system(): void
    {
        $this->upload();
        Http::fake(['*' => Http::response(['ok' => true])]);
        app(CurrentTenant::class)->clear();
        auth()->logout();

        app(CurrentTenant::class)->run($this->tenant, fn () => app(IssuerHttp::class)->send(
            $this->issuer(),
            CertificateEvent::PURPOSE_ADN_AUTHENTICATION,
            'sincronização de documentos',
            'https://adn.example.test',
            5,
            fn (PendingRequest $http) => $http->get('/DFe/0'),
        ));

        $used = $this->events()->where('type', CertificateEvent::TYPE_USED)->sole();
        $this->assertSame(CertificateEvent::PURPOSE_ADN_AUTHENTICATION, $used->purpose);
        $this->assertSame('sincronização de documentos', $used->data['operation']);
        $this->assertSame(AuditActor::TYPE_SYSTEM, $used->actor_type);
        $this->assertNull($used->ip_address);
    }

    public function test_issuer_page_shows_the_history_newest_first(): void
    {
        $this->upload();
        $this->upload();

        $this->actingAs($this->owner)
            ->get(route('invoices.issuer.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('certificateEvents', 2)
                ->where('certificateEvents.0.type', CertificateEvent::TYPE_REPLACED)
                ->where('certificateEvents.0.type_label', 'Certificado substituído')
                ->where('certificateEvents.0.actor.type', AuditActor::TYPE_USER)
                ->where('certificateEvents.1.type', CertificateEvent::TYPE_UPLOADED));
    }

    public function test_another_workspace_never_sees_this_history(): void
    {
        $this->upload();
        [$otherTenant, $otherOwner] = $this->makeInvoicesTenant('Outra');
        $this->configureIssuer($otherTenant, withCertificate: false);

        $this->actingAs($otherOwner)
            ->withSession(['current_tenant_id' => $otherTenant->getKey()])
            ->get(route('invoices.issuer.edit'))
            ->assertInertia(fn (Assert $page) => $page->has('certificateEvents', 0));
    }

    private function upload(): void
    {
        $this->actingAs($this->owner)
            ->post(route('invoices.issuer.certificate.store'), [
                'certificate' => UploadedFile::fake()->createWithContent('certificado.pfx', $this->makePkcs12(self::SUBJECT)),
                'password' => self::PKCS12_PASSWORD,
            ])
            ->assertSessionHasNoErrors();
    }

    private function issuer(): Issuer
    {
        return Issuer::query()->withoutTenantScope()->where('tenant_id', $this->tenant->getKey())->firstOrFail();
    }

    private function events()
    {
        return CertificateEvent::query()->withoutTenantScope()->where('tenant_id', $this->tenant->getKey())->orderBy('created_at')->orderBy('id')->get();
    }
}
