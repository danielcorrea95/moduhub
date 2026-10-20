<?php

namespace Tests\Feature\Billing;

use App\Models\TenantSubscription;
use App\Models\User;
use App\Modules\Billing\Models\BillingAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\InteractsWithBilling;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use InteractsWithBilling, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBilling();
    }

    public function test_card_during_trial_first_charge_at_trial_end_and_card_number_is_never_stored(): void
    {
        $this->subscribe('CREDIT_CARD')->assertSessionHasNoErrors()->assertSessionHas('success');

        $subscription = $this->subscription();
        $this->assertSame(TenantSubscription::STATUS_TRIAL, $subscription->status);
        $this->assertSame('CREDIT_CARD', $subscription->billing_type);
        $this->assertSame('1111', $subscription->card_last4);
        $this->assertNotNull($subscription->card_token);

        $remote = $this->gateway()->subscriptions[$subscription->gateway_subscription_id];
        $this->assertSame(9900, $remote['value']);
        $this->assertSame($subscription->trial_ends_at->setTimezone('America/Sao_Paulo')->format('Y-m-d'), $remote['nextDueDate']);
        $this->assertSame($subscription->getKey(), $remote['externalReference']);

        $account = BillingAccount::withoutTenantScope()->where('tenant_id', $this->tenant->getKey())->first();
        $this->assertSame('11222333000181', $account->tax_id);
        $this->assertNotNull($account->gateway_customer_id);

        // Nenhuma coluna de nenhuma tabela de cobrança guarda o número ou o CVV.
        foreach (['tenant_subscriptions', 'billing_accounts', 'billing_payments'] as $table) {
            $dump = json_encode(DB::table($table)->get());
            $this->assertStringNotContainsString('4111111111111111', $dump);
        }
    }

    public function test_refused_card_shows_the_gateway_message_and_changes_nothing(): void
    {
        $this->subscribe('CREDIT_CARD', ['card_number' => '4000 0000 0000 0002'])
            ->assertSessionHasErrors('gateway');

        $this->assertNull($this->subscription()->gateway_subscription_id);
        $this->assertNull(session()->getOldInput('card_number'));
    }

    public function test_invalid_card_number_fails_validation_before_reaching_the_gateway(): void
    {
        $this->subscribe('CREDIT_CARD', ['card_number' => '4111 1111 1111 1112'])
            ->assertSessionHasErrors('card_number');

        $this->assertSame([], $this->gateway()->subscriptions);
    }

    public function test_pix_after_trial_starts_incomplete_and_blocks_until_first_payment(): void
    {
        $this->subscription()->update(['status' => TenantSubscription::STATUS_SUSPENDED, 'trial_ends_at' => now()->subDay()]);

        $this->subscribe('PIX')->assertSessionHasNoErrors();

        $subscription = $this->subscription();
        $this->assertSame(TenantSubscription::STATUS_INCOMPLETE, $subscription->status);
        $this->assertTrue($subscription->isBlocked());
        $this->assertSame(now('America/Sao_Paulo')->format('Y-m-d'), $this->gateway()->subscriptions[$subscription->gateway_subscription_id]['nextDueDate']);

        $this->webhook('PAYMENT_RECEIVED', $this->asaasPayment('RECEIVED', now('America/Sao_Paulo')->format('Y-m-d')))->assertOk();

        $subscription->refresh();
        $this->assertSame(TenantSubscription::STATUS_ACTIVE, $subscription->status);
        $this->assertFalse($subscription->isBlocked());
        $this->assertTrue($subscription->current_period_end->isAfter(now()->addDays(27)));
    }

    public function test_cannot_subscribe_twice(): void
    {
        $this->subscribe('PIX')->assertSessionHasNoErrors();
        $this->subscribe('PIX')->assertSessionHasErrors('plan_ids');

        $this->assertCount(1, $this->gateway()->subscriptions);
    }

    public function test_change_plan_updates_the_gateway_value_and_modules(): void
    {
        $this->subscribe('PIX');
        $pro = $this->makePlan('pro', 19900, quota: 500);

        $this->asOwner()->patch(route('billing.plan'), ['plan_ids' => [$pro->id]])->assertSessionHasNoErrors();

        $subscription = $this->subscription();
        $this->assertSame([$pro->id], $subscription->plans->pluck('id')->all());
        $this->assertSame(19900, $this->gateway()->subscriptions[$subscription->gateway_subscription_id]['value']);
    }

    public function test_switch_from_pix_to_card(): void
    {
        $this->subscribe('PIX');

        $this->asOwner()->patch(route('billing.payment-method'), [...$this->accountPayload('CREDIT_CARD'), ...$this->cardPayload()])
            ->assertSessionHasNoErrors();

        $subscription = $this->subscription();
        $this->assertSame('CREDIT_CARD', $subscription->billing_type);
        $this->assertSame('VISA', $subscription->card_brand);
        $this->assertSame('CREDIT_CARD', $this->gateway()->subscriptions[$subscription->gateway_subscription_id]['billingType']);
    }

    public function test_cancel_keeps_access_until_the_end_of_the_paid_period(): void
    {
        $this->subscribe('PIX');
        $this->subscription()->update([
            'status' => TenantSubscription::STATUS_ACTIVE,
            'current_period_start' => now()->subDays(10),
            'current_period_end' => now()->addDays(20),
        ]);

        $this->asOwner()->delete(route('billing.cancel'))->assertSessionHasNoErrors();

        $subscription = $this->subscription();
        $this->assertSame(TenantSubscription::STATUS_CANCELLED, $subscription->status);
        $this->assertFalse($subscription->isBlocked());
        $this->assertTrue($this->gateway()->subscriptions[$subscription->gateway_subscription_id]['deleted']);
    }

    public function test_only_the_owner_manages_billing(): void
    {
        $admin = User::factory()->create();
        $this->tenant->users()->attach($admin, ['role' => 'admin']);

        $this->actingAs($admin)->withSession(['current_tenant_id' => $this->tenant->getKey()])
            ->get(route('billing.show'))->assertForbidden();

        $this->actingAs($admin)->withSession(['current_tenant_id' => $this->tenant->getKey()])
            ->post(route('billing.subscribe'), ['plan_ids' => [$this->plan->id], ...$this->accountPayload()])
            ->assertForbidden();
    }

    public function test_page_shows_plans_and_prefilled_account(): void
    {
        $this->asOwner()->get(route('billing.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Billing/Show')
                ->where('subscription.status', 'trial')
                ->where('products.0.key', 'signatures')
                ->where('products.0.plans.0.price_cents', 9900)
                ->where('account.email', $this->owner->email)
                ->where('account.name', 'Acme'));
    }

    public function test_cart_with_two_products_is_one_monthly_charge_with_the_sum(): void
    {
        $nfse = $this->makePlan('nfse-100', 7900, quota: 100, overageCents: 70, product: 'invoices');

        $this->subscribe('PIX', ['plan_ids' => [$this->plan->id, $nfse->id]])->assertSessionHasNoErrors();

        $subscription = $this->subscription();
        $this->assertCount(1, $this->gateway()->subscriptions);
        $this->assertSame(17800, $this->gateway()->subscriptions[$subscription->gateway_subscription_id]['value']);
        $this->assertSame(17800, $subscription->priceCents());
        $this->assertSame(100, $subscription->termsFor('invoices')->included_quota);
        $this->assertTrue($this->tenant->fresh()->hasModule('invoices'));
    }

    public function test_cart_refuses_two_plans_of_the_same_product(): void
    {
        $pro = $this->makePlan('pro', 19900, quota: 500);

        $this->subscribe('PIX', ['plan_ids' => [$this->plan->id, $pro->id]])->assertSessionHasErrors('plan_ids');
        $this->assertSame([], $this->gateway()->subscriptions);
    }

    public function test_removing_a_product_turns_its_module_off_and_kept_plans_keep_their_price(): void
    {
        $nfse = $this->makePlan('nfse-100', 7900, quota: 100, overageCents: 70, product: 'invoices');
        $this->subscribe('PIX', ['plan_ids' => [$this->plan->id, $nfse->id]]);
        $this->plan->update(['price_cents' => 12900]);

        $this->asOwner()->patch(route('billing.plan'), ['plan_ids' => [$this->plan->id]])->assertSessionHasNoErrors();

        $subscription = $this->subscription();
        $this->assertSame(9900, $subscription->priceCents());
        $this->assertSame(9900, $this->gateway()->subscriptions[$subscription->gateway_subscription_id]['value']);
        $this->assertFalse($this->tenant->fresh()->hasModule('invoices'));
    }
}
