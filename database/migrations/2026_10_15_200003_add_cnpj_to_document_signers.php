<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->string('cnpj', 14)->nullable()->after('cpf');
        });
    }

    public function down(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->dropColumn('cnpj');
        });
    }
};
