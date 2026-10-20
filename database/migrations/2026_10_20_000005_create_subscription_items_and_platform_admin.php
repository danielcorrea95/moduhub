<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Carrinho: a assinatura passa a ter um plano por produto (assinaturas, NFS-e, e-mail), todos
 * cobrados juntos numa mensalidade só. A coluna plan_id vira item e sai. E o admin da plataforma,
 * que cadastra os planos em /admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_subscription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('tenant_subscription_id')->constrained('tenant_subscriptions')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            // Preço do plano quando entrou na assinatura: mudar o preço no admin não mexe em quem já assina.
            $table->unsignedInteger('price_cents');
            $table->timestamps();
            $table->unique(['tenant_subscription_id', 'plan_id']);
        });

        DB::table('tenant_subscriptions')
            ->join('plans', 'plans.id', '=', 'tenant_subscriptions.plan_id')
            ->whereNotNull('tenant_subscriptions.plan_id')
            ->select('tenant_subscriptions.id', 'tenant_subscriptions.tenant_id', 'tenant_subscriptions.plan_id', 'plans.price_cents')
            ->orderBy('tenant_subscriptions.id')
            ->each(fn ($row) => DB::table('tenant_subscription_items')->insert([
                'tenant_id' => $row->tenant_id,
                'tenant_subscription_id' => $row->id,
                'plan_id' => $row->plan_id,
                'price_cents' => $row->price_cents,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_public');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_platform_admin'));
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn('sort_order'));

        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('tenant_id')->constrained('plans')->nullOnDelete();
        });

        // Volta só o primeiro plano de cada assinatura: o formato antigo não tem carrinho.
        DB::table('tenant_subscription_items')->orderBy('id')->each(fn ($item) => DB::table('tenant_subscriptions')
            ->where('id', $item->tenant_subscription_id)
            ->whereNull('plan_id')
            ->update(['plan_id' => $item->plan_id]));

        Schema::dropIfExists('tenant_subscription_items');
    }
};
