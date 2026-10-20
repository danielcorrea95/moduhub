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
                'description' => 'Assinatura eletrônica de documentos PDF, com selo ICP-Brasil no arquivo final.',
            ],
            [
                'key' => Module::INVOICES,
                'name' => 'Notas Fiscais',
                'description' => 'Emissão de NFS-e nacional via Padrão Nacional da NFS-e.',
            ],
            [
                'key' => Module::MAILING,
                'name' => 'Envio de E-mails',
                'description' => 'Envio de e-mails pelo SMTP e pela API da Moduhub com o domínio da empresa.',
            ],
        ];

        foreach ($modules as $data) {
            Module::updateOrCreate(['key' => $data['key']], $data + ['is_active' => true]);
        }
    }
}
