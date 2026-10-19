<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Nota que esta substitui (grupo subst da DPS). A original passa a "replaced" quando a substituta é gerada.
            $table->foreignUlid('replaces_invoice_id')->nullable()->after('creator_id')->constrained('invoices')->nullOnDelete();
            $table->string('substitution_reason_code', 2)->nullable()->after('cancellation_xml');
            $table->string('substitution_reason')->nullable()->after('substitution_reason_code');
            $table->timestamp('replaced_at')->nullable()->after('substitution_reason');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replaces_invoice_id');
            $table->dropColumn(['substitution_reason_code', 'substitution_reason', 'replaced_at']);
        });
    }
};
