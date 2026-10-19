<?php

namespace Tests\Feature\Invoices;

use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Modules\Invoices\Models\Invoice;
use App\Platform\Contracts\ApiKeyServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\InteractsWithInvoices;
use Tests\TestCase;

class InvoiceQuotaTest extends TestCase
{
    use InteractsWithInvoices, RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->owner] = $this->makeInvoicesTenant();
        $this->configureIssuer($this->tenant);
    }

    public function test_trial_blocks_after_the_quota_and_rejected_invoices_do_not_count(): void
    {
        $this->subscribe(TenantSubscription::STATUS_TRIAL, quota: 2, overage: 100);
        $key = $this->key();

        $this->sefin()->rejectNext([['code' => 'E0001', 'message' => 'Erro qualquer']]);
        $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload(['external_id' => 'a']))->assertUnprocessable();
        $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload(['external_id' => 'b']))->assertCreated();
        $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload(['external_id' => 'c']))->assertCreated();

        $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload(['external_id' => 'd']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quota']);

        $this->assertSame(3, Invoice::withoutGlobalScopes()->count(), 'Cota esgotada não reserva número de DPS.');
        $this->withToken($key)->getJson('/api/v1/invoices/usage')
            ->assertOk()
            ->assertJsonPath('data.used', 2)
            ->assertJsonPath('data.included', 2)
            ->assertJsonPath('data.remaining', 0)
            ->assertJsonPath('data.overage_allowed', false);
    }

    public function test_active_plan_with_overage_price_keeps_issuing_and_marks_the_overage(): void
    {
        $this->subscribe(TenantSubscription::STATUS_ACTIVE, quota: 1, overage: 100);
        $key = $this->key();

        $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload(['external_id' => 'a']))
            ->assertCreated()
            ->assertJsonPath('data.is_overage', false);
        $this->withToken($key)->postJson('/api/v1/invoices', $this->invoicePayload(['external_id' => 'b']))
            ->assertCreated()
            ->assertJsonPath('data.is_overage', true);

        $this->actingAs($this->owner)
            ->get(route('invoices.index'))
            ->assertInertia(fn (Assert $page) => $page->where('usage.used', 2)->where('usage.included', 1)->where('usage.overage_allowed', true));
    }

    public function test_without_subscription_there_is_no_limit(): void
    {
        $this->withToken($this->key())->getJson('/api/v1/invoices/usage')
            ->assertOk()
            ->assertJsonPath('data.included', null)
            ->assertJsonPath('data.remaining', null);
    }

    private function subscribe(string $status, int $quota, ?int $overage): void
    {
        $plan = Plan::create(['key' => 'teste', 'name' => 'Teste', 'price_cents' => 1000]);
        $module = Module::firstOrCreate(['key' => Module::INVOICES], ['name' => 'Notas Fiscais']);
        $plan->modules()->attach($module, ['included_quota' => $quota, 'overage_cents' => $overage]);

        TenantSubscription::create([
            'tenant_id' => $this->tenant->getKey(),
            'plan_id' => $plan->id,
            'status' => $status,
            'current_period_start' => now()->subDays(3),
            'current_period_end' => now()->addDays(27),
        ]);
    }

    private function key(): string
    {
        return app(ApiKeyServiceContract::class)->generate($this->tenant, $this->owner, 'Teste', ['invoices:*'])['plaintext'];
    }
}
