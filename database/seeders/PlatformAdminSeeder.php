<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Cria (ou promove) o admin da plataforma, que cadastra os planos em /admin/planos.
 *
 *   PLATFORM_ADMIN_EMAIL=voce@moduhub.com.br PLATFORM_ADMIN_PASSWORD=... php artisan db:seed --class=PlatformAdminSeeder
 *
 * Usuário que já existe só ganha a marca de admin; a senha dele não muda. Sem senha no .env, um
 * usuário novo recebe uma senha aleatória, mostrada uma única vez no terminal.
 */
class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower(trim((string) env('PLATFORM_ADMIN_EMAIL')));
        if ($email === '') {
            $this->command?->error('Defina PLATFORM_ADMIN_EMAIL (e de preferência PLATFORM_ADMIN_PASSWORD).');

            return;
        }

        $user = User::where('email', $email)->first();

        if ($user !== null) {
            $user->forceFill(['is_platform_admin' => true])->save();
            $this->command?->info("{$email} agora é admin da plataforma (senha mantida).");

            return;
        }

        $password = (string) env('PLATFORM_ADMIN_PASSWORD') ?: Str::password(20);

        User::forceCreate([
            'name' => (string) env('PLATFORM_ADMIN_NAME', 'Admin Moduhub'),
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'is_platform_admin' => true,
        ]);

        $this->command?->info("Admin {$email} criado.");
        if (! env('PLATFORM_ADMIN_PASSWORD')) {
            $this->command?->warn("Senha gerada (anote, não aparece de novo): {$password}");
        }
    }
}
