<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('issuer_id')->constrained('invoice_issuers')->cascadeOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            // Identificador do pedido no sistema do cliente (CRM, ERP). Também serve de chave de idempotência na API.
            $table->string('external_id')->nullable();

            $table->string('status', 20);
            $table->string('environment', 20);

            // A DPS é a declaração que o emitente assina; a Sefin devolve a NFS-e gerada a partir dela.
            $table->string('dps_series', 5);
            $table->unsignedBigInteger('dps_number');
            $table->string('dps_id', 45);
            $table->date('competence_date');
            $table->dateTime('emitted_at');

            $table->string('taker_document', 14)->nullable();
            $table->string('taker_name');
            $table->json('taker');
            $table->json('service');
            $table->decimal('amount', 15, 2);
            $table->decimal('iss_rate', 5, 2)->nullable();
            $table->boolean('iss_withheld')->default(false);
            $table->unsignedTinyInteger('iss_taxation')->default(1);
            // Percentuais aproximados de tributos federal/estadual/municipal (Lei 12.741), grupo totTrib da DPS.
            $table->json('approximate_taxes')->nullable();

            $table->string('access_key', 50)->nullable();
            $table->string('nfse_number', 15)->nullable();
            $table->dateTime('issued_at')->nullable();
            $table->longText('dps_xml')->nullable();
            $table->longText('nfse_xml')->nullable();
            $table->json('rejection')->nullable();
            $table->text('last_error')->nullable();
            $table->dateTime('last_attempt_at')->nullable();

            $table->unsignedTinyInteger('cancel_reason_code')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->longText('cancellation_xml')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'external_id']);
            $table->index('dps_id');
            $table->index('access_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
