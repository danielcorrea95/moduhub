<?php

namespace Tests\Feature\Billing;

use App\Models\Module;
use App\Models\Plan;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\InteractsWithBilling;
use Tests\TestCase;

/** Do botão do plano na página de vendas até a tela de assinatura. */
class SalesPageTest extends TestCase
{
    use InteractsWithBilling, RefreshDatabase;

    public function test_home_shows_the_free_email_quota(): void
    {
        config(['mailing.quota.free.daily' => 100, 'mailing.quota.free.monthly' => 3000]);

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('mailingFreeQuota.daily', 100)
            ->where('mailingFreeQuota.monthly', 3000));
    }

    public function test_plan_chosen_on_the_sales_page_starts_the_trial(): void
    {
        $this->makePlan('starter', 9900);
        $pro = $this->makePlan('pro', 19900, quota: 500);

        $nfse = $this->makePlan('nfse-100', 7900, quota: 100, product: 'invoices');

        $this->get(route('register', ['plans' => 'pro,nfse-100']))
            ->assertInertia(fn (Assert $page) => $page->has('plans', 2)->where('plans.0.key', 'nfse-100')->where('plans.1.key', 'pro'));

        $this->post(route('register'), [
            'name' => 'Fulana',
            'email' => 'fulana@acme.com.br',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
            'plans' => ['pro', 'nfse-100'],
        ])->assertRedirect(route('dashboard', absolute: false));

        $tenantId = User::where('email', 'fulana@acme.com.br')->sole()->tenants()->sole()->getKey();
        $subscription = TenantSubscription::withoutTenantScope()->where('tenant_id', $tenantId)->sole();
        $this->assertEqualsCanonicalizing([$pro->id, $nfse->id], $subscription->plans->pluck('id')->all());
        $this->assertSame(TenantSubscription::STATUS_TRIAL, $subscription->status);
    }

    public function test_trial_without_a_choice_gets_the_entry_plan_of_each_product(): void
    {
        $this->makePlan('starter', 9900);
        $this->makePlan('pro', 19900, quota: 500);
        $nfse = $this->makePlan('nfse-100', 7900, quota: 100, product: 'invoices');

        $this->post(route('register'), [
            'name' => 'Fulana',
            'email' => 'fulana@acme.com.br',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
        ]);

        $tenantId = User::where('email', 'fulana@acme.com.br')->sole()->tenants()->sole()->getKey();
        $subscription = TenantSubscription::withoutTenantScope()->where('tenant_id', $tenantId)->sole();
        $this->assertEqualsCanonicalizing(['starter', 'nfse-100'], $subscription->plans->pluck('key')->all());
    }

    public function test_hidden_plan_cannot_be_chosen_from_the_url(): void
    {
        $this->makePlan('starter', 9900);
        Plan::create(['key' => 'interno', 'name' => 'Interno', 'price_cents' => 0, 'is_public' => false]);

        $this->get(route('register', ['plans' => 'interno']))->assertInertia(fn (Assert $page) => $page->has('plans', 0));
        $this->post(route('register'), [
            'name' => 'Fulana',
            'email' => 'fulana@acme.com.br',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
            'plans' => ['interno'],
        ])->assertSessionHasErrors('plans.0');
    }

    public function test_billing_page_preselects_the_cart_from_the_link(): void
    {
        $this->setUpBilling();
        $pro = $this->makePlan('pro', 19900, quota: 500);
        $this->makePlan('nfse-100', 7900, quota: 100, product: 'invoices');
        // Combo antigo (dois produtos num plano) fica fora do carrinho.
        $combo = Plan::create(['key' => 'combo', 'name' => 'Combo', 'price_cents' => 19900]);
        $combo->modules()->attach([
            Module::firstOrCreate(['key' => Module::INVOICES], ['name' => 'Notas'])->id => ['included_quota' => 100],
            Module::firstOrCreate(['key' => Module::SIGNATURES], ['name' => 'Assinaturas'])->id => ['included_quota' => 10],
        ]);

        $this->asOwner()->get(route('billing.show', ['plans' => 'pro']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('preselected_plan_ids', [$pro->id])
                ->has('products', 2));
    }
}
