<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // vReceb: quanto o intermediário recebeu do tomador, quando difere do valor do serviço.
            $table->decimal('intermediary_received', 15, 2)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('intermediary_received');
        });
    }
};
