<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trilha de auditoria do certificado do emitente: quem enviou, trocou ou removeu, e cada vez que a
 * chave privada foi aberta e para quê. Só acrescenta linhas; nada aqui é editado depois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_certificate_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('issuer_id')->constrained('invoice_issuers')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('purpose', 48)->nullable();
            // Identifica qual certificado estava em uso, já que o emitente troca de A1 todo ano.
            $table->char('certificate_fingerprint', 64)->nullable();
            $table->string('certificate_serial', 80)->nullable();
            $table->string('actor_type', 16);
            $table->string('actor_id', 36)->nullable();
            // Cópia do nome no momento: o usuário ou a chave podem ser apagados depois.
            $table->string('actor_label')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('data')->nullable();
            $table->timestamp('created_at');

            $table->index(['issuer_id', 'created_at']);
        });

        Schema::table('invoice_issuers', function (Blueprint $table) {
            // Menor aviso de vencimento já enviado para o certificado atual (30, 15, 7, 1 ou 0 = vencido).
            $table->unsignedSmallInteger('certificate_expiry_notice_days')->nullable()->after('certificate_uploaded_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_issuers', function (Blueprint $table) {
            $table->dropColumn('certificate_expiry_notice_days');
        });

        Schema::dropIfExists('invoice_certificate_events');
    }
};
