<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uma linha por destinatário de cada e-mail que saiu pelo SES. O SES informa entrega, bounce e
 * reclamação por destinatário, e é isso que prova que o convite de assinatura chegou (ou não).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_deliveries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // Nulo para e-mail da plataforma sem workspace (alertas internos).
            $table->foreignUlid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('message_id', 100);
            $table->string('recipient');
            $table->string('mailable')->nullable();
            $table->string('subject')->nullable();
            $table->string('status', 16);
            $table->string('bounce_type', 32)->nullable();
            $table->string('detail', 500)->nullable();
            $table->timestamp('sent_at');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('complained_at')->nullable();
            $table->timestamps();

            $table->unique(['message_id', 'recipient']);
            $table->index(['tenant_id', 'recipient']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_deliveries');
    }
};
