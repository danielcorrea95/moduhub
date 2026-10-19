<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Grupo tribFed já resolvido (valores calculados a partir das alíquotas quando vieram só elas).
            $table->json('federal_taxes')->nullable()->after('immunity_type');
            // Benefício municipal (BM) e exigibilidade suspensa do ISSQN (exigSusp).
            $table->json('municipal_benefit')->nullable()->after('federal_taxes');
            $table->json('iss_suspension')->nullable()->after('municipal_benefit');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['federal_taxes', 'municipal_benefit', 'iss_suspension']);
        });
    }
};
