<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cota de e-mail: o plano pode limitar por dia (daily_quota) e por mês (included_quota). O módulo
 * `mailing` nasce aqui, e não só no seeder, porque a contagem precisa dele em produção.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_module', function (Blueprint $table) {
            $table->unsignedInteger('daily_quota')->nullable()->after('included_quota');
        });

        DB::table('modules')->insertOrIgnore([
            'key' => 'mailing',
            'name' => 'Envio de E-mails',
            'description' => 'Envio de e-mails pelo SMTP e pela API da Moduhub com o domínio da empresa.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('modules')->where('key', 'mailing')->delete();

        Schema::table('plan_module', function (Blueprint $table) {
            $table->dropColumn('daily_quota');
        });
    }
};
