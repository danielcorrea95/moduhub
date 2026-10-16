<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            // Papel livre de quem assina: "Diretor", "Responsável financeiro", "Testemunha"...
            $table->string('role', 60)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
