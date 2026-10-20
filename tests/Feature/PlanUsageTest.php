<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Modules\Mailing\Models\SendingDomain;
use App\Modules\Mailing\Services\Quota\EmailQuotaService;
use App\Modules\Signatures\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlanUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_gets_one_meter_per_module_in_use(): void
    {
        [$tenant, $owner] = $this->makeTenant();
        $plan = Plan::create(['key' => 'completo', 'name' => 'Completo', 'price_cents' => 19900]);
        foreach ([Module::SIGNATURES => 50, Module::INVOICES => 100] as $key => $quota) {
            $module = Module::firstOrCreate(['key' => $key], ['name' => $key]);
            $plan->modules()->attach($module, ['included_quota' => $quota]);
            $tenant->modules()->attach($module, ['source' => 'plan', 'is_active' => true, 'activated_at' => now()]);
        }
        TenantSubscription::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'plan_id' => $plan->getKey(),
            'status' => TenantSubscription::STATUS_ACTIVE,
            'current_period_start' => now()->subDays(5),
            'current_period_end' => now()->addDays(25),
        ]);

        $this->document($tenant, sentAt: now()->subDay());
        $this->document($tenant, sentAt: now()->subDays(10));
        $this->document($tenant, sentAt: null);

        SendingDomain::withoutTenantScope()->create([
            'tenant_id' => $tenant->getKey(), 'domain' => 'acme.com.br', 'status' => SendingDomain::STATUS_VERIFIED,
            'dkim_tokens' => [], 'from_name' => 'Acme', 'from_local_part' => 'contato', 'verified_at' => now(),
        ]);
        app(EmailQuotaService::class)->reserve($tenant, 2);

        $this->actingAs($owner)->withSession(['current_tenant_id' => $tenant->getKey()])
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('planUsage', 3)
                ->where('planUsage.0.key', 'signatures')
                ->where('planUsage.0.used', 1)
                ->where('planUsage.0.limit', 50)
                ->where('planUsage.1.key', 'invoices')
                ->where('planUsage.1.used', 0)
                ->where('planUsage.1.limit', 100)
                ->where('planUsage.2.key', 'mailing')
                ->where('planUsage.2.used', 2)
                ->where('planUsage.2.limit', 3000)
                ->where('planUsage.2.detail', 'Hoje: 2 de 100'));
    }

    public function test_workspace_without_modules_or_domain_shows_nothing(): void
    {
        [$tenant, $owner] = $this->makeTenant();

        $this->actingAs($owner)->withSession(['current_tenant_id' => $tenant->getKey()])
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('planUsage', []));
    }

    private function document(Tenant $tenant, $sentAt): void
    {
        Document::withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Contrato '.Str::random(4),
            'status' => $sentAt ? Document::STATUS_SENT : Document::STATUS_DRAFT,
            'sent_at' => $sentAt,
            'file_path' => 'x.pdf',
            'file_hash' => str_repeat('0', 64),
            'file_size' => 1024,
        ]);
    }

    /** @return array{0: Tenant, 1: User} */
    private function makeTenant(): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme-'.Str::random(4), 'status' => 'active']);
        $tenant->users()->attach($user, ['role' => 'owner']);

        return [$tenant, $user];
    }
}
