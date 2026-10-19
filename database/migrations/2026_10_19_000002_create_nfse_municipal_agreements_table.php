<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Convênio de cada município com o Sistema Nacional NFS-e, lido do ADN. Dado público e global: sem tenant.
        Schema::create('nfse_municipal_agreements', function (Blueprint $table) {
            $table->id();
            $table->char('city_code', 7);
            $table->string('environment', 20);
            $table->string('status', 20);
            $table->boolean('national_environment')->nullable();
            $table->boolean('national_emitter')->nullable();
            $table->boolean('rfb_standard_issuance')->nullable();
            $table->boolean('national_assessment')->nullable();
            $table->boolean('credit_use')->nullable();
            $table->string('message')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->unique(['city_code', 'environment'], 'nfse_agreements_city_env_unique');
            $table->index(['environment', 'status', 'national_emitter'], 'nfse_agreements_situation_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfse_municipal_agreements');
    }
};
