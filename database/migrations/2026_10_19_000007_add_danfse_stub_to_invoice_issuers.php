<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_issuers', function (Blueprint $table) {
            // Canhoto do DANFSe (NT 008, item 2.1.13): opcional, para quem entrega a nota impressa e colhe assinatura.
            $table->boolean('danfse_stub')->default(false)->after('email_taker');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_issuers', function (Blueprint $table) {
            $table->dropColumn('danfse_stub');
        });
    }
};
