<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usuário e senha que o cliente coloca no sistema dele para enviar pelo smtp.moduhub.com.br.
 * A senha só existe em claro na hora de criar; aqui fica o SHA-256 (segredo aleatório de 40
 * caracteres, como as chaves de API), que o relay SMTP confere a cada AUTH.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smtp_credentials', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('username', 40)->unique();
            $table->char('password_hash', 64);
            $table->string('last_four', 4);
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smtp_credentials');
    }
};
