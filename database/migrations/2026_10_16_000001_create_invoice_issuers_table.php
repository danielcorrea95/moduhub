<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_issuers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // Um emitente por workspace nesta fase. Quando um tenant precisar emitir por vários CNPJs,
            // basta trocar o unique por um índice simples e passar a identificar o emitente nas rotas.
            $table->foreignUlid('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();

            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            // CNPJ sem máscara. 14 posições comportam também o formato alfanumérico adotado em 2026.
            $table->string('cnpj', 14);
            $table->string('municipal_registration', 15)->nullable();
            // Código IBGE do município emissor (cLocEmi da DPS).
            $table->string('city_code', 7);
            $table->string('city_name', 120)->nullable();
            $table->string('state', 2)->nullable();

            // Enquadramento tributário conforme o grupo regTrib da DPS nacional.
            $table->unsignedTinyInteger('simples_nacional');
            $table->unsignedTinyInteger('simples_regime')->nullable();
            $table->unsignedTinyInteger('special_regime')->default(0);

            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();

            $table->string('environment', 20)->default('restricted');
            $table->string('dps_series', 5)->default('1');
            $table->unsignedBigInteger('next_dps_number')->default(1);

            // O .pfx e a senha ficam cifrados pelo cast `encrypted`; os demais campos são só metadados
            // para mostrar validade e titular sem precisar abrir o certificado.
            $table->mediumText('certificate_pfx')->nullable();
            $table->text('certificate_password')->nullable();
            $table->string('certificate_subject')->nullable();
            $table->string('certificate_cnpj', 14)->nullable();
            $table->string('certificate_issuer')->nullable();
            $table->string('certificate_serial', 64)->nullable();
            $table->string('certificate_fingerprint', 64)->nullable();
            $table->timestamp('certificate_valid_from')->nullable();
            $table->timestamp('certificate_valid_until')->nullable();
            $table->timestamp('certificate_uploaded_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_issuers');
    }
};
