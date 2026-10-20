<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abertura e clique (eventos Open/Click do configuration set), o histórico de cada e-mail e a
 * lista de endereços que o workspace não deve mais receber envios (bounce permanente e spam).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_deliveries', function (Blueprint $table) {
            $table->string('from_address')->nullable()->after('recipient');
            $table->string('provider', 24)->nullable()->after('from_address');
            $table->timestamp('opened_at')->nullable()->after('complained_at');
            $table->unsignedInteger('open_count')->default(0)->after('opened_at');
            $table->timestamp('clicked_at')->nullable()->after('open_count');
            $table->unsignedInteger('click_count')->default(0)->after('clicked_at');

            $table->index(['tenant_id', 'sent_at']);
        });

        Schema::create('email_delivery_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUlid('email_delivery_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            // Milissegundos: o SNS reentrega a mesma notificação, e o horário exato do evento é o que a deduplica.
            $table->timestamp('occurred_at', 3);
            $table->string('detail', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['email_delivery_id', 'type', 'occurred_at']);
        });

        Schema::create('email_suppressions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('reason', 16);
            $table->string('detail', 500)->nullable();
            $table->foreignUlid('email_delivery_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_suppressions');
        Schema::dropIfExists('email_delivery_events');

        Schema::table('email_deliveries', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'sent_at']);
            $table->dropColumn(['from_address', 'provider', 'opened_at', 'open_count', 'clicked_at', 'click_count']);
        });
    }
};
