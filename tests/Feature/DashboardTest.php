<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\Issuer;
use App\Modules\Mailing\Models\SendingDomain;
use App\Platform\Contracts\ApiKeyServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\InteractsWithInvoices;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use InteractsWithInvoices, RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->owner] = $this->makeInvoicesTenant();
        $this->configureIssuer($this->tenant);
        // Meio do mês, para "mês passado" não cair na virada. Para frente: o certificado de teste começa a valer hoje.
        $this->travelTo(now('America/Sao_Paulo')->addMonthNoOverflow()->setDay(15)->setTime(12, 0));
    }

    public function test_owner_sees_spend_with_overage_and_issued_amount_by_month(): void
    {
        $this->subscribe(quota: 1, overage: 70);
        $key = app(ApiKeyServiceContract::class)->generate($this->tenant, $this->owner, 'Teste', ['invoices:*'])['plaintext'];
        foreach (['a', 'b', 'c'] as $id) {
            $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload(['external_id' => $id]))->assertCreated();
        }
        // Uma das notas foi emitida no mês passado: sai do mês atual e entra no anterior.
        Invoice::withoutGlobalScopes()->where('external_id', 'c')->update(['issued_at' => now()->subMonth()]);

        $this->dashboard($this->owner)->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('billing.plan', 'Completo')
            ->where('billing.lines.0', ['label' => 'Plano Completo', 'cents' => 9900])
            ->where('billing.lines.1', ['label' => '2 notas excedentes', 'cents' => 140])
            ->where('billing.total_cents', 10040)
            ->has('sections.invoices.months', 6)
            ->where('sections.invoices.months.5.count', 2)
            ->where('sections.invoices.months.5.total_cents', 300000)
            ->where('sections.invoices.months.4.total_cents', 150000)
            ->where('sections.invoices.pace.today', 15)
            ->has('sections.invoices.pace.current', 15)
            ->where('sections.invoices.pace.current.14', 300000)
            ->where('sections.invoices.pace.previous.14', 150000)
            ->missing('sections.signatures'));
    }

    public function test_alerts_collect_problems_from_every_module_dangers_first(): void
    {
        Issuer::query()->withoutTenantScope()->where('tenant_id', $this->tenant->getKey())->update(['certificate_valid_until' => now()->addDays(10)]);
        SendingDomain::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->getKey(), 'domain' => 'acme.com.br', 'status' => SendingDomain::STATUS_FAILED,
            'dkim_tokens' => [], 'from_name' => 'Acme', 'from_local_part' => 'contato', 'verified_at' => now()->subMonth(),
        ]);

        $this->dashboard($this->owner)->assertInertia(fn (Assert $page) => $page
            ->has('alerts', 2)
            ->where('alerts.0.tone', 'danger')
            ->where('alerts.0.title', 'O domínio acme.com.br parou de funcionar')
            ->where('alerts.1.tone', 'warning')
            ->where('alerts.1.title', fn (string $title) => str_starts_with($title, 'O certificado digital vence em'))
            ->where('sections.mailing.domain.status', 'failed'));
    }

    public function test_members_see_their_modules_but_not_spend_or_email(): void
    {
        $this->subscribe(quota: 10, overage: null);
        SendingDomain::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->getKey(), 'domain' => 'acme.com.br', 'status' => SendingDomain::STATUS_VERIFIED,
            'dkim_tokens' => [], 'from_name' => 'Acme', 'from_local_part' => 'contato', 'verified_at' => now(),
        ]);
        $member = User::factory()->create();
        $this->tenant->users()->attach($member, ['role' => 'member']);

        $this->dashboard($member)->assertInertia(fn (Assert $page) => $page
            ->where('billing', null)
            ->has('sections.invoices')
            ->missing('sections.mailing'));
    }

    private function dashboard(User $user)
    {
        return $this->actingAs($user)->withSession(['current_tenant_id' => $this->tenant->getKey()])->get(route('dashboard'))->assertOk();
    }

    private function subscribe(int $quota, ?int $overage): void
    {
        $plan = Plan::create(['key' => 'completo', 'name' => 'Completo', 'price_cents' => 9900]);
        $module = Module::firstOrCreate(['key' => Module::INVOICES], ['name' => 'Notas Fiscais']);
        $plan->modules()->attach($module, ['included_quota' => $quota, 'overage_cents' => $overage]);

        TenantSubscription::create([
            'tenant_id' => $this->tenant->getKey(),
            'status' => TenantSubscription::STATUS_ACTIVE,
            'current_period_start' => now()->subDays(3),
            'current_period_end' => now()->addDays(27),
        ])->plans()->attach($plan->id, ['tenant_id' => $this->tenant->getKey(), 'price_cents' => $plan->price_cents]);
    }
}
