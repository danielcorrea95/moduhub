<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $signatures = Module::where('key', Module::SIGNATURES)->firstOrFail();
        $invoices = Module::where('key', Module::INVOICES)->firstOrFail();

        $starter = Plan::updateOrCreate(
            ['key' => 'starter'],
            [
                'name' => 'Starter',
                'description' => 'Para pequenas empresas começando.',
                'price_cents' => 9900,
                'currency' => 'BRL',
                'billing_period' => 'monthly',
                'trial_days' => 14,
                'is_active' => true,
                'is_public' => true,
            ],
        );
        $starter->modules()->syncWithoutDetaching([
            $signatures->id => ['included_quota' => 50, 'overage_cents' => 150],
            $invoices->id => ['included_quota' => 100, 'overage_cents' => 100],
        ]);

        $signaturesOnly = Plan::updateOrCreate(
            ['key' => 'signatures-pro'],
            [
                'name' => 'Signatures Pro',
                'description' => 'Apenas assinaturas, volume alto.',
                'price_cents' => 14900,
                'currency' => 'BRL',
                'billing_period' => 'monthly',
                'trial_days' => 14,
                'is_active' => true,
                'is_public' => true,
            ],
        );
        $signaturesOnly->modules()->syncWithoutDetaching([
            $signatures->id => ['included_quota' => 500, 'overage_cents' => 100],
        ]);

        $invoicesOnly = Plan::updateOrCreate(
            ['key' => 'invoices-pro'],
            [
                'name' => 'Invoices Pro',
                'description' => 'Apenas notas fiscais, volume alto.',
                'price_cents' => 19900,
                'currency' => 'BRL',
                'billing_period' => 'monthly',
                'trial_days' => 14,
                'is_active' => true,
                'is_public' => true,
            ],
        );
        $invoicesOnly->modules()->syncWithoutDetaching([
            $invoices->id => ['included_quota' => 1000, 'overage_cents' => 80],
        ]);
    }
}
