<?php

namespace Tests\Feature;

use App\Models\Plan;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_public_plans_cheapest_first_and_flags_unreleased_modules(): void
    {
        $this->seed([ModuleSeeder::class, PlanSeeder::class]);
        Plan::create(['key' => 'interno', 'name' => 'Interno', 'price_cents' => 1, 'is_active' => true, 'is_public' => false]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->has('plans', 3)
                ->where('plans.0.key', 'starter')
                ->where('plans.0.modules', fn ($modules) => collect($modules)->pluck('released', 'key')->all() === [
                    'signatures' => true,
                    'invoices' => false,
                ]));
    }
}
