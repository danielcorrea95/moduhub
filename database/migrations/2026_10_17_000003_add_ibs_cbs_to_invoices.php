<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Classificação do IBS/CBS que o contador define para o emitente; cada nota pode sobrescrever.
        Schema::table('invoice_issuers', function (Blueprint $table) {
            $table->string('ibs_cbs_cst', 3)->nullable()->after('special_regime');
            $table->string('ibs_cbs_class_code', 6)->nullable()->after('ibs_cbs_cst');
            $table->string('ibs_cbs_operation_code', 6)->nullable()->after('ibs_cbs_class_code');
        });

        // Códigos efetivamente declarados na DPS desta nota.
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('ibs_cbs')->nullable()->after('approximate_taxes');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_issuers', function (Blueprint $table) {
            $table->dropColumn(['ibs_cbs_cst', 'ibs_cbs_class_code', 'ibs_cbs_operation_code']);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('ibs_cbs');
        });
    }
};
