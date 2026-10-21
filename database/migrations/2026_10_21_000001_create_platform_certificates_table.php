<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * e-CNPJ da Moduhub enviado pelo admin (selo dos documentos e consultas ao ADN). Cada envio é uma
 * linha; a atual é a que não foi aposentada. Ao aposentar, o .pfx e a senha são apagados e a linha
 * fica só como histórico de quem trocou, quando e de onde.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_certificates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // Cifrados com a CERTIFICATES_ENCRYPTION_KEY (cast CertificateEncrypted), como os dos emitentes.
            $table->text('pfx')->nullable();
            $table->text('password')->nullable();
            $table->string('subject');
            $table->string('cnpj', 14);
            $table->string('issuer');
            $table->string('serial', 80);
            $table->char('fingerprint', 64);
            $table->timestamp('valid_from');
            $table->timestamp('valid_until');
            // Cópia do nome no momento: o usuário pode ser apagado depois.
            $table->string('uploaded_by_id', 36)->nullable();
            $table->string('uploaded_by_label')->nullable();
            $table->string('uploaded_ip', 45)->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->string('retired_reason', 16)->nullable();
            $table->string('retired_by_id', 36)->nullable();
            $table->string('retired_by_label')->nullable();
            $table->string('retired_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['retired_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_certificates');
    }
};
