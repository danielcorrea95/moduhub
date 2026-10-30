<?php

namespace Tests\Feature\Admin;

use App\Mail\CustomerWelcomeMail;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Modules\Billing\Services\BillingCycleService;
use App\Modules\Billing\Services\ComplimentaryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\InteractsWithBilling;
use Tests\TestCase;

class CustomerAdminTest extends TestCase
{
    use InteractsWithBilling, RefreshDatabase;

    private User $admin;

    private Tenant $adminTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBilling();
        Mail::fake();

        // O admin navega com o próprio workspace na sessão, como em produção.
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_platform_admin' => true])->save();
        $this->adminTenant = Tenant::create(['name' => 'Moduhub', 'slug' => 'moduhub-'.Str::random(4), 'status' => 'active']);
        $this->adminTenant->users()->attach($this->admin, ['role' => 'owner']);
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin)->withSession(['current_tenant_id' => $this->adminTenant->getKey()]);
    }

    private function currentFor(Tenant $tenant): TenantSubscription
    {
        return TenantSubscription::withoutTenantScope()->where('tenant_id', $tenant->getKey())->orderByDesc('id')->with('plans')->first();
    }

    public function test_only_platform_admins_reach_customers(): void
    {
        $this->get(route('admin.customers.index'))->assertRedirect(route('login'));
        $this->asOwner()->get(route('admin.customers.index'))->assertForbidden();
        $this->asOwner()->post(route('admin.customers.store'), [])->assertForbidden();
        $this->asAdmin()->get(route('admin.customers.index'))->assertOk();
    }

    public function test_lists_every_workspace_and_finds_by_owner_email(): void
    {
        $this->asAdmin()->get(route('admin.customers.index'))->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Customers/Index')
            ->has('customers.data', 2));

        $this->asAdmin()->get(route('admin.customers.index', ['busca' => $this->owner->email]))->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 1)
            ->where('customers.data.0.name', 'Acme')
            ->where('customers.data.0.subscription.status', TenantSubscription::STATUS_TRIAL)
            ->where('customers.data.0.owners.0.email', $this->owner->email));
    }

    public function test_registers_customer_with_new_owner_in_complimentary(): void
    {
        $hidden = $this->makePlan('nfse-cortesia', 0, quota: 5000, product: Module::INVOICES);
        $hidden->update(['is_public' => false]);

        $this->asAdmin()->post(route('admin.customers.store'), [
            'workspace_name' => 'Auren',
            'tax_id' => '',
            'owner_name' => 'Fulano',
            'owner_email' => ' Fulano@Auren.com.br ',
            'plan_ids' => [$this->plan->id, $hidden->id],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $tenant = Tenant::where('name', 'Auren')->sole();
        $owner = User::where('email', 'fulano@auren.com.br')->sole();
        $this->assertNotNull($owner->email_verified_at);
        $this->assertSame('owner', $owner->tenantRole($tenant));
        $this->assertFalse($this->admin->belongsToTenant($tenant));

        $subscription = $this->currentFor($tenant);
        $this->assertSame(TenantSubscription::STATUS_COMPLIMENTARY, $subscription->status);
        $this->assertNull($subscription->trial_ends_at);
        $this->assertNull($subscription->gateway_subscription_id);
        $this->assertSame(1, TenantSubscription::withoutTenantScope()->where('tenant_id', $tenant->getKey())->count());
        $this->assertEqualsCanonicalizing([$this->plan->id, $hidden->id], $subscription->plans->pluck('id')->all());
        $this->assertSame(0, $subscription->priceCents());
        $this->assertTrue($tenant->hasModule(Module::SIGNATURES));
        $this->assertTrue($tenant->hasModule(Module::INVOICES));
        // Nada vazou para o workspace da sessão do admin.
        $this->assertSame(0, TenantSubscription::withoutTenantScope()->where('tenant_id', $this->adminTenant->getKey())->count());

        Mail::assertSent(CustomerWelcomeMail::class, fn (CustomerWelcomeMail $mail) => $mail->hasTo('fulano@auren.com.br')
            && str_contains((string) $mail->setPasswordUrl, '/reset-password/'));
    }

    public function test_existing_account_gets_the_workspace_without_password_link(): void
    {
        $this->asAdmin()->post(route('admin.customers.store'), [
            'workspace_name' => 'Igreja',
            'owner_name' => 'Outro nome',
            'owner_email' => $this->admin->email,
            'plan_ids' => [$this->plan->id],
        ])->assertSessionHasNoErrors();

        $tenant = Tenant::where('name', 'Igreja')->sole();
        $this->assertSame('owner', $this->admin->tenantRole($tenant));
        $this->assertSame(1, User::where('email', $this->admin->email)->count());
        Mail::assertSent(CustomerWelcomeMail::class, fn (CustomerWelcomeMail $mail) => $mail->setPasswordUrl === null);
    }

    public function test_one_plan_per_product(): void
    {
        $other = $this->makePlan('pro', 19900);

        $this->asAdmin()->post(route('admin.customers.store'), [
            'workspace_name' => 'Auren',
            'owner_name' => 'Fulano',
            'owner_email' => 'fulano@auren.com.br',
            'plan_ids' => [$this->plan->id, $other->id],
        ])->assertSessionHasErrors('plan_ids');

        $this->assertFalse(Tenant::where('name', 'Auren')->exists());
        Mail::assertNothingSent();
    }

    public function test_complimentary_never_blocks_nor_bills_and_owner_cannot_subscribe(): void
    {
        $this->asAdmin()->put(route('admin.customers.complimentary.grant', $this->tenant), ['plan_ids' => [$this->plan->id]])
            ->assertSessionHasNoErrors();

        $subscription = $this->subscription();
        $this->assertSame(TenantSubscription::STATUS_COMPLIMENTARY, $subscription->status);
        $this->assertSame(0, (int) $subscription->plans()->first()->pivot->price_cents);

        $this->assertSame(0, app(BillingCycleService::class)->run(CarbonImmutable::now()->addYear()));
        $this->assertSame(TenantSubscription::STATUS_COMPLIMENTARY, $this->subscription()->status);
        $this->assertFalse($this->subscription()->isBlocked());

        $this->subscribe('PIX')->assertSessionHasErrors('plan_ids');
        $this->asOwner()->patch(route('billing.plan'), ['plan_ids' => [$this->makePlan('pro', 19900)->id]])->assertSessionHasErrors('plan_ids');
        $this->assertNull($this->subscription()->gateway_subscription_id);

        $this->asOwner()->get(route('billing.show'))->assertInertia(fn (Assert $page) => $page
            ->where('subscription.status', TenantSubscription::STATUS_COMPLIMENTARY)
            ->where('subscription.blocked', false));
    }

    public function test_paying_workspace_cannot_get_complimentary(): void
    {
        $this->subscribe('PIX')->assertSessionHasNoErrors();

        $this->asAdmin()->put(route('admin.customers.complimentary.grant', $this->tenant), ['plan_ids' => [$this->plan->id]])
            ->assertSessionHasErrors('plan_ids');

        $this->assertNotSame(TenantSubscription::STATUS_COMPLIMENTARY, $this->subscription()->status);
    }

    public function test_ending_complimentary_starts_a_short_trial(): void
    {
        $this->asAdmin()->put(route('admin.customers.complimentary.grant', $this->tenant), ['plan_ids' => [$this->plan->id]]);

        $this->asAdmin()->delete(route('admin.customers.complimentary.end', $this->tenant))->assertSessionHasNoErrors();

        $subscription = $this->subscription();
        $this->assertSame(TenantSubscription::STATUS_TRIAL, $subscription->status);
        $this->assertTrue($subscription->trial_ends_at->isSameDay(now()->addDays(ComplimentaryService::ENDING_TRIAL_DAYS)));
        $this->assertTrue($this->tenant->hasModule(Module::SIGNATURES));

        $this->asAdmin()->delete(route('admin.customers.complimentary.end', $this->tenant))->assertSessionHasErrors('subscription');
    }
}
