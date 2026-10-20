<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cobrança das assinaturas pelo Asaas: o cadastro de cobrança do workspace (cliente no Asaas), as
 * cobranças (mensalidade e excedente) e os eventos do webhook, guardados para não processar a
 * mesma entrega duas vezes. Número de cartão nunca é gravado: só bandeira, final e o token do Asaas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            $table->string('billing_type')->nullable()->after('status');
            $table->string('gateway_subscription_id')->nullable()->unique()->after('billing_type');
            $table->string('card_brand')->nullable()->after('gateway_subscription_id');
            $table->string('card_last4', 4)->nullable()->after('card_brand');
            // Só existe com a tokenização habilitada na conta do Asaas; cobra o excedente no cartão.
            $table->text('card_token')->nullable()->after('card_last4');
            $table->date('past_due_since')->nullable()->after('cancelled_at');
            $table->timestamp('suspended_at')->nullable()->after('past_due_since');
        });

        Schema::create('billing_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->string('gateway_customer_id')->nullable()->unique();
            $table->string('name');
            $table->string('tax_id', 14);
            $table->string('email');
            $table->string('phone', 11);
            $table->string('postal_code', 8);
            $table->string('address_number', 20);
            $table->string('address_complement', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('billing_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('tenant_subscription_id')->nullable()->constrained('tenant_subscriptions')->nullOnDelete();
            $table->string('kind');
            $table->string('gateway_payment_id')->nullable()->unique();
            $table->string('billing_type')->nullable();
            $table->string('status');
            $table->unsignedInteger('value_cents');
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->string('invoice_url')->nullable();
            $table->string('description')->nullable();
            // Excedente: o ciclo cobrado e as linhas (rótulo e valor), para mostrar e não cobrar duas vezes.
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->json('lines')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->unique(['tenant_id', 'kind', 'period_start']);
        });

        Schema::create('billing_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway_event_id')->unique();
            $table->string('event');
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_webhook_events');
        Schema::dropIfExists('billing_payments');
        Schema::dropIfExists('billing_accounts');

        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            $table->dropUnique(['gateway_subscription_id']);
            $table->dropColumn([
                'billing_type', 'gateway_subscription_id', 'card_brand', 'card_last4', 'card_token',
                'past_due_since', 'suspended_at',
            ]);
        });
    }
};
