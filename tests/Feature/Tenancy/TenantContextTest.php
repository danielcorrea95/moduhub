<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Modules\Invoices\Models\Issuer;
use App\Tenancy\CurrentTenant;
use App\Tenancy\MissingTenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Support\InteractsWithInvoices;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    use InteractsWithInvoices, RefreshDatabase;

    public function test_scoped_query_without_tenant_fails_instead_of_returning_every_workspace(): void
    {
        [$first] = $this->makeInvoicesTenant('Primeira');
        [$second] = $this->makeInvoicesTenant('Segunda');
        $this->configureIssuer($first, withCertificate: false);
        $this->configureIssuer($second, withCertificate: false);
        app(CurrentTenant::class)->clear();

        $this->assertSame(2, Issuer::query()->withoutTenantScope()->count(), 'Atravessar workspaces continua possível quando é explícito.');

        $this->expectException(MissingTenantContext::class);
        Issuer::query()->count();
    }

    public function test_run_scopes_the_callback_and_restores_the_previous_context(): void
    {
        [$first] = $this->makeInvoicesTenant('Primeira');
        [$second] = $this->makeInvoicesTenant('Segunda');
        $this->configureIssuer($first, withCertificate: false);
        $this->configureIssuer($second, withCertificate: false);
        $tenants = app(CurrentTenant::class);
        $tenants->clear();

        $seen = $tenants->run($second, fn () => Issuer::query()->pluck('tenant_id')->all());

        $this->assertSame([$second->getKey()], $seen);
        $this->assertFalse($tenants->check(), 'O contexto anterior (nenhum) volta depois do callback.');
    }

    public function test_run_refuses_to_switch_to_another_tenant(): void
    {
        [$first] = $this->makeInvoicesTenant('Primeira');
        [$second] = $this->makeInvoicesTenant('Segunda');
        $tenants = app(CurrentTenant::class);
        $tenants->set($first);

        $this->assertTrue($tenants->run($first, fn () => true), 'Reentrar no mesmo tenant é permitido.');

        $this->expectException(LogicException::class);
        $tenants->run($second, fn () => null);
    }

    public function test_queued_job_runs_in_the_tenant_that_dispatched_it(): void
    {
        config(['queue.default' => 'database']);
        [$first] = $this->makeInvoicesTenant('Primeira');
        [$second] = $this->makeInvoicesTenant('Segunda');
        $this->configureIssuer($first, withCertificate: false);
        $issuer = $this->configureIssuer($second, withCertificate: false);
        $tenants = app(CurrentTenant::class);
        $tenants->clear();

        // Closure com corpo, e não arrow function: o PendingDispatch só enfileira ao ser destruído, e
        // devolvido pela arrow function ele sobreviveria ao run() e sairia sem tenant.
        $tenants->run($second, function () use ($issuer) {
            RecordTenantContext::dispatch($issuer);
        });
        $this->assertSame($second->getKey(), json_decode(DB::table('jobs')->value('payload'), true)['tenant_id']);

        // O worker começa sem contexto, como em produção: o tenant vem só do payload.
        RecordTenantContext::$seen = null;
        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true])->assertSuccessful();

        $this->assertSame(['tenant' => $second->getKey(), 'issuers' => [$second->getKey()]], RecordTenantContext::$seen);
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_job_dispatched_outside_any_tenant_runs_without_one(): void
    {
        config(['queue.default' => 'database']);
        [$tenant] = $this->makeInvoicesTenant();
        app(CurrentTenant::class)->clear();

        RecordTenantContext::dispatch(null);
        $this->assertArrayNotHasKey('tenant_id', json_decode(DB::table('jobs')->value('payload'), true));

        RecordTenantContext::$seen = null;
        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true])->assertSuccessful();

        $this->assertSame(['tenant' => null, 'issuers' => null], RecordTenantContext::$seen);
        $this->assertInstanceOf(Tenant::class, $tenant);
    }
}

class RecordTenantContext implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    /** @var array{tenant: ?string, issuers: ?list<string>}|null */
    public static ?array $seen = null;

    public function __construct(public ?Issuer $issuer) {}

    public function handle(CurrentTenant $tenants): void
    {
        self::$seen = [
            'tenant' => $tenants->id(),
            'issuers' => $tenants->check() ? Issuer::query()->pluck('tenant_id')->all() : null,
        ];
    }
}
