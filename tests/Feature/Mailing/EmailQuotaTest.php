<?php

namespace Tests\Feature\Mailing;

use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Modules\Mailing\Exceptions\EmailQuotaExceeded;
use App\Modules\Mailing\Services\Quota\EmailQuotaService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmailQuotaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private EmailQuotaService $quota;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mailing.quota.free' => ['daily' => 3, 'monthly' => 5]]);
        Carbon::setTestNow('2026-10-20 15:00:00');
        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme-'.Str::random(4), 'status' => 'active']);
        $this->quota = app(EmailQuotaService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_free_tier_blocks_on_the_daily_limit_and_frees_up_the_next_day(): void
    {
        foreach (range(1, 3) as $_) {
            $this->quota->reserve($this->tenant);
        }

        $error = $this->blocked();
        $this->assertSame(EmailQuotaExceeded::DAILY, $error->window);
        $this->assertSame('Limite diário de 3 e-mails atingido. O envio volta à meia-noite (horário de Brasília).', $error->getMessage());
        $this->assertSame(3, $this->quota->usage($this->tenant)->dailyUsed);

        Carbon::setTestNow('2026-10-21 15:00:00');
        $usage = $this->quota->reserve($this->tenant);

        $this->assertSame(1, $usage->dailyUsed);
        $this->assertSame(4, $usage->monthlyUsed);
        $this->assertTrue($usage->isFree());
    }

    public function test_free_tier_blocks_on_the_monthly_limit_until_the_next_month(): void
    {
        $this->reserveOn('2026-10-02 12:00:00', 3);
        $this->reserveOn('2026-10-20 12:00:00', 2);

        $error = $this->blocked();
        $this->assertSame(EmailQuotaExceeded::MONTHLY, $error->window);
        $this->assertSame('2026-11-01', $error->resetsAt->toDateString());
        $this->assertStringContainsString('O envio volta em 01/11/2026', $error->getMessage());

        Carbon::setTestNow('2026-11-01 12:00:00');
        $this->assertSame(1, $this->quota->reserve($this->tenant)->monthlyUsed);
    }

    public function test_the_day_turns_at_midnight_in_brasilia(): void
    {
        // 02:30 UTC ainda é 23:30 do dia 19 em Brasília.
        $this->reserveOn('2026-10-20 02:30:00', 3);

        Carbon::setTestNow('2026-10-20 03:30:00');
        $usage = $this->quota->reserve($this->tenant);

        $this->assertSame(1, $usage->dailyUsed);
        $this->assertSame('2026-10-21T00:00:00-03:00', $usage->dayResetsAt->toIso8601String());
    }

    public function test_plan_with_the_mailing_module_uses_its_own_limits_and_cycle(): void
    {
        $this->subscribe(daily: null, monthly: 4, start: '2026-10-10 09:00:00', end: '2026-11-10 09:00:00');

        // Dias antes do ciclo não contam.
        $this->reserveOn('2026-10-05 12:00:00', 3);

        Carbon::setTestNow('2026-10-20 15:00:00');
        foreach (range(1, 4) as $_) {
            $usage = $this->quota->reserve($this->tenant);
        }

        $this->assertSame('Empresarial', $usage->plan);
        $this->assertNull($usage->dailyLimit);
        $this->assertSame(4, $usage->dailyUsed);

        $error = $this->blocked();
        $this->assertSame(EmailQuotaExceeded::MONTHLY, $error->window);
        $this->assertSame('2026-11-10', $error->resetsAt->toDateString());
    }

    public function test_plan_without_the_mailing_module_stays_on_the_free_tier(): void
    {
        $plan = Plan::create(['key' => 'so-assinatura', 'name' => 'Só assinatura', 'price_cents' => 9900]);
        TenantSubscription::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getKey(),
            'status' => TenantSubscription::STATUS_ACTIVE,
            'current_period_start' => now()->subDays(3),
            'current_period_end' => now()->addDays(27),
        ])->plans()->attach($plan->getKey(), ['tenant_id' => $this->tenant->getKey(), 'price_cents' => $plan->price_cents]);

        $usage = $this->quota->usage($this->tenant);

        $this->assertTrue($usage->isFree());
        $this->assertSame(3, $usage->dailyLimit);
        $this->assertSame(5, $usage->monthlyLimit);
    }

    public function test_release_gives_back_messages_whose_send_failed(): void
    {
        $this->quota->reserve($this->tenant);
        $this->quota->reserve($this->tenant);
        $this->quota->release($this->tenant);

        $this->assertSame(1, $this->quota->usage($this->tenant)->dailyUsed);
    }

    public function test_panel_shows_the_usage(): void
    {
        $owner = User::factory()->create();
        $this->tenant->users()->attach($owner, ['role' => 'owner']);
        $this->quota->reserve($this->tenant);

        $this->actingAs($owner)->withSession(['current_tenant_id' => $this->tenant->getKey()])
            ->get(route('sending-domain.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('usage.free', true)
                ->where('usage.daily.used', 1)
                ->where('usage.daily.limit', 3)
                ->where('usage.monthly.limit', 5)
                ->where('usage.monthly.remaining', 4));
    }

    private function blocked(): EmailQuotaExceeded
    {
        try {
            $this->quota->reserve($this->tenant);
        } catch (EmailQuotaExceeded $e) {
            return $e;
        }

        $this->fail('O envio deveria ter sido bloqueado.');
    }

    private function reserveOn(string $moment, int $messages): void
    {
        Carbon::setTestNow($moment);
        CarbonImmutable::setTestNow($moment);
        $this->quota->reserve($this->tenant, $messages);
    }

    private function subscribe(?int $daily, ?int $monthly, string $start, string $end): void
    {
        $module = Module::query()->where('key', Module::MAILING)->firstOrFail();
        $plan = Plan::create(['key' => 'empresarial', 'name' => 'Empresarial', 'price_cents' => 19900]);
        $plan->modules()->attach($module, ['daily_quota' => $daily, 'included_quota' => $monthly]);

        TenantSubscription::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getKey(),
            'status' => TenantSubscription::STATUS_ACTIVE,
            'current_period_start' => $start,
            'current_period_end' => $end,
        ])->plans()->attach($plan->getKey(), ['tenant_id' => $this->tenant->getKey(), 'price_cents' => $plan->price_cents]);
    }
}
