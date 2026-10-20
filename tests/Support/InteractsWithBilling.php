<?php

namespace Tests\Support;

use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Modules\Billing\Contracts\PaymentGatewayContract;
use App\Modules\Billing\Services\Gateway\FakePaymentGateway;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/** Workspace com plano de assinaturas (cota 2, excedente R$ 1,50) em teste grátis, e o Asaas fake. */
trait InteractsWithBilling
{
    protected Tenant $tenant;

    protected User $owner;

    protected Plan $plan;

    protected function setUpBilling(): void
    {
        config(['services.asaas.driver' => 'fake', 'services.asaas.webhook_token' => 'token-do-webhook-de-teste-com-32-caracteres', 'services.asaas.grace_days' => 7]);

        $this->owner = User::factory()->create();
        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme-'.Str::random(4), 'status' => 'active']);
        $this->tenant->users()->attach($this->owner, ['role' => 'owner']);

        $this->plan = $this->makePlan('starter', 9900);
        $module = Module::firstOrCreate(['key' => Module::SIGNATURES], ['name' => 'Assinaturas']);
        $this->tenant->modules()->attach($module, ['source' => 'plan', 'is_active' => true, 'activated_at' => now()]);

        TenantSubscription::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getKey(),
            'status' => TenantSubscription::STATUS_TRIAL,
            'trial_ends_at' => now()->addDays(10),
            'current_period_start' => now()->subDays(4),
            'current_period_end' => now()->addDays(10),
        ])->plans()->attach($this->plan->id, ['tenant_id' => $this->tenant->getKey(), 'price_cents' => $this->plan->price_cents]);
    }

    /** Plano de um produto só, como o admin cadastra. */
    protected function makePlan(string $key, int $priceCents, int $quota = 2, int $overageCents = 150, string $product = Module::SIGNATURES): Plan
    {
        $plan = Plan::create(['key' => $key, 'name' => ucfirst($key), 'price_cents' => $priceCents, 'trial_days' => 14]);
        $module = Module::firstOrCreate(['key' => $product], ['name' => $product]);
        $plan->modules()->attach($module, ['included_quota' => $quota, 'overage_cents' => $overageCents]);

        return $plan;
    }

    protected function gateway(): FakePaymentGateway
    {
        return app(PaymentGatewayContract::class);
    }

    protected function subscription(): TenantSubscription
    {
        return TenantSubscription::withoutTenantScope()->where('tenant_id', $this->tenant->getKey())->orderByDesc('id')->first();
    }

    protected function asOwner(): static
    {
        return $this->actingAs($this->owner)->withSession(['current_tenant_id' => $this->tenant->getKey()]);
    }

    /** @return array<string, string> */
    protected function accountPayload(string $billingType = 'PIX'): array
    {
        return [
            'billing_type' => $billingType,
            'name' => 'Acme Ltda',
            'tax_id' => '11.222.333/0001-81',
            'email' => 'financeiro@acme.com.br',
            'phone' => '(51) 99999-0000',
            'postal_code' => '93510-000',
            'address_number' => '100',
            'address_complement' => '',
        ];
    }

    /** @return array<string, string> */
    protected function cardPayload(string $number = '4111 1111 1111 1111'): array
    {
        return [
            'card_holder_name' => 'FULANO DE TAL',
            'card_number' => $number,
            'card_expiry_month' => '5',
            'card_expiry_year' => (string) (now()->year + 3),
            'card_cvv' => '123',
        ];
    }

    protected function subscribe(string $billingType = 'PIX', array $extra = []): TestResponse
    {
        return $this->asOwner()->post(route('billing.subscribe'), [
            'plan_ids' => [$this->plan->id],
            ...$this->accountPayload($billingType),
            ...($billingType === 'CREDIT_CARD' ? $this->cardPayload() : []),
            ...$extra,
        ]);
    }

    /** @param  array<string, mixed>  $payment */
    protected function webhook(string $event, array $payment, ?string $eventId = null, ?string $token = null): TestResponse
    {
        return $this->postJson(route('webhooks.asaas'), [
            'id' => $eventId ?? 'evt_'.Str::random(10),
            'event' => $event,
            'dateCreated' => now()->format('Y-m-d H:i:s'),
            'payment' => $payment,
        ], ['asaas-access-token' => $token ?? 'token-do-webhook-de-teste-com-32-caracteres']);
    }

    /** Cobrança da assinatura como o Asaas manda no webhook. */
    protected function asaasPayment(string $status, string $dueDate, array $overrides = []): array
    {
        $subscription = $this->subscription();
        $customer = \App\Modules\Billing\Models\BillingAccount::withoutTenantScope()->where('tenant_id', $this->tenant->getKey())->value('gateway_customer_id');

        return [
            'object' => 'payment',
            'id' => 'pay_mensal_1',
            'customer' => $customer,
            'subscription' => $subscription->gateway_subscription_id,
            'value' => 99.0,
            'billingType' => $subscription->billing_type ?? 'PIX',
            'status' => $status,
            'dueDate' => $dueDate,
            'paymentDate' => $status === 'RECEIVED' ? now()->format('Y-m-d') : null,
            'invoiceUrl' => 'https://sandbox.asaas.com/i/pay_mensal_1',
            'description' => 'Moduhub - plano Starter',
            'externalReference' => null,
            'deleted' => false,
            ...$overrides,
        ];
    }
}
