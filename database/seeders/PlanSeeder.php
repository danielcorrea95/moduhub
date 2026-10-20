<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Planos de exemplo para desenvolvimento, um por produto, com a grade de preços anotada em out/2026.
 * Em produção, os planos são cadastrados em /admin/planos; não rode este seeder lá.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // Combos do começo do projeto: saem da venda, mas continuam valendo para quem já assina.
        Plan::whereIn('key', ['starter', 'signatures-pro', 'invoices-pro'])->update(['is_active' => false, 'is_public' => false]);

        $plans = [
            // [produto, chave, nome, descrição, preço, cota mensal, cota diária, excedente, ordem]
            [Module::SIGNATURES, 'assinaturas-essencial', 'Essencial', 'Para quem fecha alguns contratos por semana.', 9900, 50, null, 150, 10],
            [Module::SIGNATURES, 'assinaturas-profissional', 'Profissional', 'Para equipes comerciais e escritórios.', 14900, 500, null, 100, 20],
            [Module::INVOICES, 'nfse-100', 'NFS-e 100', 'Até 100 notas por mês.', 7900, 100, null, 70, 10],
            [Module::INVOICES, 'nfse-300', 'NFS-e 300', 'Até 300 notas por mês.', 16900, 300, null, 55, 20],
            [Module::INVOICES, 'nfse-1000', 'NFS-e 1.000', 'Até 1.000 notas por mês.', 29900, 1000, null, 35, 30],
            [Module::MAILING, 'email-10mil', 'E-mail 10 mil', 'Até 10 mil e-mails por mês.', 2490, 10000, null, null, 10],
            [Module::MAILING, 'email-50mil', 'E-mail 50 mil', 'Até 50 mil e-mails por mês.', 9900, 50000, null, null, 20],
            [Module::MAILING, 'email-100mil', 'E-mail 100 mil', 'Até 100 mil e-mails por mês.', 17900, 100000, null, null, 30],
            [Module::MAILING, 'email-300mil', 'E-mail 300 mil', 'Até 300 mil e-mails por mês.', 49900, 300000, null, null, 40],
        ];

        foreach ($plans as [$product, $key, $name, $description, $price, $quota, $daily, $overage, $order]) {
            $plan = Plan::updateOrCreate(['key' => $key], [
                'name' => $name,
                'description' => $description,
                'price_cents' => $price,
                'currency' => 'BRL',
                'billing_period' => 'monthly',
                'trial_days' => 14,
                'is_active' => true,
                'is_public' => true,
                'sort_order' => $order,
            ]);

            $plan->modules()->sync([
                Module::where('key', $product)->firstOrFail()->id => [
                    'included_quota' => $quota,
                    'daily_quota' => $daily,
                    'overage_cents' => $overage,
                ],
            ]);
        }
    }
}
