<?php

namespace Tests\Feature\Admin;

use App\Models\Module;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PlatformAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlanAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([Module::SIGNATURES, Module::INVOICES, Module::MAILING] as $key) {
            Module::firstOrCreate(['key' => $key], ['name' => $key]);
        }
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_platform_admin' => true])->save();
    }

    public function test_only_platform_admins_reach_the_admin(): void
    {
        $this->get(route('admin.plans.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.plans.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.plans.index'))->assertOk();
    }

    public function test_admin_creates_a_mailing_plan_with_prices_in_reais(): void
    {
        $this->actingAs($this->admin)->post(route('admin.plans.store'), [
            'product' => 'mailing',
            'name' => 'E-mail 10 mil',
            'price' => '24,90',
            'trial_days' => '14',
            'included_quota' => '10.000',
            'daily_quota' => '1000',
            'overage' => '',
            'is_active' => true,
            'is_public' => true,
        ])->assertRedirect(route('admin.plans.index'))->assertSessionHasNoErrors();

        $plan = Plan::with('modules')->where('name', 'E-mail 10 mil')->sole();
        $this->assertSame('mailing-e-mail-10-mil', $plan->key);
        $this->assertSame(2490, $plan->price_cents);
        $this->assertSame('mailing', $plan->product()->key);
        $this->assertSame(10000, $plan->modules->first()->pivot->included_quota);
        $this->assertSame(1000, $plan->modules->first()->pivot->daily_quota);
        $this->assertNull($plan->modules->first()->pivot->overage_cents);

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('products.0.key', 'mailing')
            ->where('products.0.plans.0.price_cents', 2490));
    }

    public function test_invalid_price_is_a_form_error(): void
    {
        $this->actingAs($this->admin)->post(route('admin.plans.store'), [
            'product' => 'invoices', 'name' => 'NFS-e', 'price' => 'setenta', 'trial_days' => '14',
        ])->assertSessionHasErrors('price_cents');
    }

    public function test_product_cannot_change_once_the_plan_has_subscribers(): void
    {
        $this->actingAs($this->admin)->post(route('admin.plans.store'), [
            'product' => 'invoices', 'name' => 'NFS-e 100', 'price' => '79', 'trial_days' => '14', 'included_quota' => '100', 'overage' => '0,70',
            'is_active' => true, 'is_public' => true,
        ]);
        $plan = Plan::where('name', 'NFS-e 100')->sole();
        $plan->subscriptionItems()->forceCreate([
            'tenant_id' => \App\Models\Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active'])->getKey(),
            'tenant_subscription_id' => \App\Models\TenantSubscription::withoutGlobalScopes()->forceCreate([
                'tenant_id' => \App\Models\Tenant::first()->getKey(), 'status' => 'active',
            ])->getKey(),
            'price_cents' => 7900,
        ]);

        $this->actingAs($this->admin)->put(route('admin.plans.update', $plan), [
            'product' => 'mailing', 'name' => 'NFS-e 100', 'price' => '89', 'trial_days' => '14', 'is_active' => true, 'is_public' => true,
        ])->assertSessionHasErrors('product');

        $this->actingAs($this->admin)->put(route('admin.plans.update', $plan), [
            'product' => 'invoices', 'name' => 'NFS-e 100', 'price' => '89', 'trial_days' => '14', 'included_quota' => '120', 'overage' => '0,70',
            'is_active' => true, 'is_public' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame(8900, $plan->fresh()->price_cents);
        // Quem já assinava continua pagando o preço contratado.
        $this->assertSame(7900, $plan->subscriptionItems()->value('price_cents'));
    }

    public function test_seeder_promotes_an_existing_user_without_touching_the_password(): void
    {
        $user = User::factory()->create(['email' => 'dono@moduhub.com.br', 'password' => Hash::make('senha-antiga')]);
        putenv('PLATFORM_ADMIN_EMAIL=dono@moduhub.com.br');

        try {
            $this->seed(PlatformAdminSeeder::class);
        } finally {
            putenv('PLATFORM_ADMIN_EMAIL');
        }

        $user->refresh();
        $this->assertTrue($user->isPlatformAdmin());
        $this->assertTrue(Hash::check('senha-antiga', $user->password));
    }
}
