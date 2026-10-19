<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Último NSU lido na distribuição do ADN, por ambiente: a próxima varredura continua dali.
        Schema::table('invoice_issuers', function (Blueprint $table) {
            $table->json('dfe_cursor')->nullable()->after('email_taker');
            $table->timestamp('dfe_synced_at')->nullable()->after('dfe_cursor');
        });

        // Cada documento distribuído pelo ADN (NFS-e e eventos), como chegou. É o arquivo fiscal do workspace.
        Schema::create('nfse_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('issuer_id')->constrained('invoice_issuers')->cascadeOnDelete();
            $table->string('environment', 20);
            $table->unsignedBigInteger('nsu');
            $table->char('access_key', 50);
            $table->string('document_type', 30);
            $table->string('event_type', 60)->nullable();
            $table->longText('xml');
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['issuer_id', 'environment', 'nsu'], 'nfse_documents_nsu_unique');
            $table->index(['tenant_id', 'access_key']);
        });

        // NFS-e de terceiros em que o workspace aparece como tomador, intermediário ou prestador (nota emitida pelo tomador).
        Schema::create('received_invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('environment', 20);
            $table->char('access_key', 50);
            $table->string('role', 20);
            $table->string('status', 20);
            $table->string('provider_document', 20)->nullable();
            $table->string('provider_name')->nullable();
            $table->string('taker_document', 40)->nullable();
            $table->string('taker_name')->nullable();
            $table->string('nfse_number', 20)->nullable();
            $table->date('competence_date')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('iss_amount', 15, 2)->nullable();
            $table->string('description', 2000)->nullable();
            $table->json('manifestation')->nullable();
            $table->longText('nfse_xml');
            $table->timestamps();

            $table->unique(['tenant_id', 'environment', 'access_key'], 'received_invoices_key_unique');
            $table->index(['tenant_id', 'status']);
        });

        // Situação das nossas notas vinda de eventos de terceiros ou do fisco.
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('cancellation_review')->nullable()->after('cancellation_xml');
            $table->json('taker_manifestation')->nullable()->after('cancellation_review');
            $table->timestamp('blocked_at')->nullable()->after('taker_manifestation');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['cancellation_review', 'taker_manifestation', 'blocked_at']);
        });
        Schema::dropIfExists('received_invoices');
        Schema::dropIfExists('nfse_documents');
        Schema::table('invoice_issuers', function (Blueprint $table) {
            $table->dropColumn(['dfe_cursor', 'dfe_synced_at']);
        });
    }
};
