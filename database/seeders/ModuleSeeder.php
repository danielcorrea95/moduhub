<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            [
                'key' => Module::SIGNATURES,
                'name' => 'Assinatura de Documentos',
                'description' => 'Assinatura eletrônica e digital (ICP-Brasil) de documentos PDF.',
            ],
            [
                'key' => Module::INVOICES,
                'name' => 'Notas Fiscais',
                'description' => 'Emissão de NFS-e nacional via Padrão Nacional da NFS-e.',
            ],
        ];

        foreach ($modules as $data) {
            Module::updateOrCreate(['key' => $data['key']], $data + ['is_active' => true]);
        }
    }
}
