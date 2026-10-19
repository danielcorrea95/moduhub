<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Documentos que comprovam a dedução da base (vDedRed/documentos), alternativa a deduction_amount/percent.
            $table->json('deduction_documents')->nullable()->after('deduction_percent');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('deduction_documents');
        });
    }
};
