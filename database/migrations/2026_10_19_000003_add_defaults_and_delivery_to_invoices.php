<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_issuers', function (Blueprint $table) {
            // Serviço que o emitente presta quase sempre (escola: mensalidade). A nota pode omitir e herdar.
            $table->json('default_service')->nullable()->after('ibs_cbs_operation_code');
            // Percentuais aproximados da Lei 12.741 (tabela IBPT) usados quando a nota não informa.
            $table->json('default_approximate_taxes')->nullable()->after('default_service');
            $table->boolean('email_taker')->default(true)->after('default_approximate_taxes');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('notify_taker')->default(false)->after('ibs_cbs');
            $table->timestamp('taker_emailed_at')->nullable()->after('notify_taker');
            $table->string('taker_email_error')->nullable()->after('taker_emailed_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_issuers', function (Blueprint $table) {
            $table->dropColumn(['default_service', 'default_approximate_taxes', 'email_taker']);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['notify_taker', 'taker_emailed_at', 'taker_email_error']);
        });
    }
};
