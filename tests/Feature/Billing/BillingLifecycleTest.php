<?php

namespace Tests\Feature\Billing;

use App\Models\TenantSubscription;
use App\Modules\Billing\Models\BillingPayment;
use App\Modules\Billing\Models\BillingWebhookEvent;
use App\Modules\Billing\Services\BillingCycleService;
use App\Modules\Signatures\Models\Document;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithBilling;
use Tests\TestCase;

class BillingLifecycleTest extends TestCase
{
    use InteractsWithBilling, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBilling();
    }

    public function test_webhook_without_the_right_token_is_refused(): void
    {
        $this->subscribe('PIX');

        $this->webhook('PAYMENT_RECEIVED', $this->asaasPayment('RECEIVED', now()->format('Y-m-d')), token: 'errado')->assertForbidden();
        config(['services.asaas.webhook_token' => '']);
        $this->webhook('PAYMENT_RECEIVED', $this->asaasPayment('RECEIVED', now()->format('Y-m-d')), token: '')->assertForbidden();

        $this->assertSame(0, BillingPayment::withoutTenantScope()->count());
    }

    public function test_overdue_payment_puts_in_arrears_and_payment_brings_back(): void
    {
        $this->subscribe('PIX');
        $this->activate();
        $due = now()->subDays(2)->format('Y-m-d');

        $this->webhook('PAYMENT_OVERDUE', $this->asaasPayment('OVERDUE', $due), 'evt_1')->assertOk();
        $this->webhook('PAYMENT_OVERDUE', $this->asaasPayment('OVERDUE', $due), 'evt_1')->assertOk(); // reentrega

        $subscription = $this->subscription();
        $this->assertSame(TenantSubscription::STATUS_PAST_DUE, $subscription->status);
        $this->assertSame($due, $subscription->past_due_since->format('Y-m-d'));
        $this->assertSame(1, BillingWebhookEvent::count());
        $this->assertFalse($subscription->isBlocked());

        $this->webhook('PAYMENT_RECEIVED', $this->asaasPayment('RECEIVED', $due))->assertOk();

        $subscription->refresh();
        $this->assertSame(TenantSubscription::STATUS_ACTIVE, $subscription->status);
        $this->assertNull($subscription->past_due_since);
        $this->assertSame(1, BillingPayment::withoutTenantScope()->count());
    }

    public function test_arrears_beyond_grace_period_blocks_and_payment_unblocks(): void
    {
        $this->subscribe('PIX');
        $this->activate();
        $due = now()->subDays(8)->format('Y-m-d');
        $this->webhook('PAYMENT_OVERDUE', $this->asaasPayment('OVERDUE', $due))->assertOk();

        app(BillingCycleService::class)->run();

        $this->assertSame(TenantSubscription::STATUS_SUSPENDED, $this->subscription()->status);
        $this->asOwner()->from(route('signatures.index'))->post(route('signatures.store'), [])
            ->assertRedirect(route('signatures.index'))
            ->assertSessionHas('error');
        $this->asOwner()->get(route('signatures.index'))->assertOk();

        $this->webhook('PAYMENT_RECEIVED', $this->asaasPayment('RECEIVED', $due))->assertOk();

        $this->assertSame(TenantSubscription::STATUS_ACTIVE, $this->subscription()->status);
    }

    public function test_trial_ending_without_subscription_blocks(): void
    {
        $this->subscription()->update(['trial_ends_at' => now()->subMinute()]);

        app(BillingCycleService::class)->run();

        $subscription = $this->subscription();
        $this->assertSame(TenantSubscription::STATUS_SUSPENDED, $subscription->status);
        $this->assertTrue($subscription->isBlocked());
    }

    public function test_trial_ending_with_subscription_opens_the_first_cycle(): void
    {
        $this->subscribe('CREDIT_CARD');
        $end = CarbonImmutable::now()->subMinute();
        $this->subscription()->update(['trial_ends_at' => $end]);

        app(BillingCycleService::class)->run();

        $subscription = $this->subscription();
        $this->assertSame(TenantSubscription::STATUS_ACTIVE, $subscription->status);
        $this->assertSame($end->format('Y-m-d H:i'), $subscription->current_period_start->format('Y-m-d H:i'));
        $this->assertSame($end->addMonthNoOverflow()->format('Y-m-d H:i'), $subscription->current_period_end->format('Y-m-d H:i'));
    }

    public function test_closing_a_cycle_charges_overage_once_on_the_saved_card_and_opens_the_next(): void
    {
        $this->subscribe('CREDIT_CARD');
        $start = CarbonImmutable::now()->subMonth()->subDay();
        $end = CarbonImmutable::now()->subDay();
        $this->subscription()->update([
            'status' => TenantSubscription::STATUS_ACTIVE,
            'trial_ends_at' => $start->subDay(),
            'current_period_start' => $start,
            'current_period_end' => $end,
        ]);
        // Cota 2 a R$ 1,50: 6 enviados = 4 excedentes = R$ 6,00. O de fora do ciclo não conta.
        foreach (range(1, 6) as $i) {
            $this->document($start->addDays($i));
        }
        $this->document($start->subDay());

        app(BillingCycleService::class)->run();
        app(BillingCycleService::class)->run();

        $charges = $this->gateway()->charges;
        $this->assertCount(1, $charges);
        $charge = array_values($charges)[0];
        $this->assertSame(600, $charge['valueCents']);
        $this->assertSame('CREDIT_CARD', $charge['billingType']);
        $this->assertSame($this->subscription()->card_token, $charge['cardToken']);

        $payment = BillingPayment::withoutTenantScope()->where('kind', BillingPayment::KIND_OVERAGE)->sole();
        $this->assertSame('4 documentos excedentes', $payment->lines[0]['label']);
        $this->assertSame($payment->getKey(), $charge['externalReference']);

        $subscription = $this->subscription();
        $this->assertSame($end->format('Y-m-d H:i'), $subscription->current_period_start->format('Y-m-d H:i'));
        $this->assertTrue($subscription->current_period_end->isFuture());

        // O webhook do excedente acha a cobrança pela nossa referência.
        $this->webhook('PAYMENT_RECEIVED', $this->asaasPayment('RECEIVED', now()->format('Y-m-d'), [
            'id' => $payment->gateway_payment_id, 'subscription' => null, 'externalReference' => $payment->getKey(), 'value' => 6.0,
        ]))->assertOk();
        $this->assertSame(BillingPayment::STATUS_PAID, $payment->refresh()->status);
        $this->assertSame(1, BillingPayment::withoutTenantScope()->count());
    }

    public function test_overage_below_the_minimum_charge_is_not_billed(): void
    {
        $this->subscribe('PIX');
        $start = CarbonImmutable::now()->subMonth()->subDay();
        $this->subscription()->update([
            'status' => TenantSubscription::STATUS_ACTIVE,
            'trial_ends_at' => $start->subDay(),
            'current_period_start' => $start,
            'current_period_end' => CarbonImmutable::now()->subDay(),
        ]);
        foreach (range(1, 3) as $i) {
            $this->document($start->addDays($i));
        }

        app(BillingCycleService::class)->run();

        $this->assertSame([], $this->gateway()->charges);
    }

    private function activate(): void
    {
        $this->subscription()->update([
            'status' => TenantSubscription::STATUS_ACTIVE,
            'current_period_start' => now()->subDays(10),
            'current_period_end' => now()->addDays(20),
        ]);
    }

    private function document(CarbonImmutable $sentAt): void
    {
        Document::withoutGlobalScopes()->forceCreate([
            'tenant_id' => $this->tenant->getKey(),
            'name' => 'Contrato '.Str::random(4),
            'status' => Document::STATUS_SENT,
            'sent_at' => $sentAt,
            'file_path' => 'x.pdf',
            'file_hash' => str_repeat('0', 64),
            'file_size' => 1024,
        ]);
    }
}
