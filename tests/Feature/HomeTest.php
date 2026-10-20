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

    public function test_lists_the_three_products_with_their_public_plans_cheapest_first(): void
    {
        $this->seed([ModuleSeeder::class, PlanSeeder::class]);
        $hidden = Plan::create(['key' => 'interno', 'name' => 'Interno', 'price_cents' => 1, 'is_active' => true, 'is_public' => false]);
        $hidden->modules()->attach(\App\Models\Module::where('key', 'signatures')->value('id'));

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->has('products', 3)
                ->where('products', fn ($products) => collect($products)->pluck('key')->all() === ['signatures', 'invoices', 'mailing'])
                ->where('products.0.plans.0.key', 'assinaturas-essencial')
                ->where('products.1.plans', fn ($plans) => collect($plans)->pluck('price_cents')->all() === [7900, 16900, 29900])
                ->where('products.2.plans.0.included_quota', 10000));
    }
}
