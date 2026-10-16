<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_signers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedSmallInteger('order')->default(1);
            $table->string('name');
            $table->string('email');
            $table->string('cpf', 14)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('auth_method')->default('email');
            $table->string('status')->default('pending');
            $table->string('token')->unique();
            $table->string('verification_code')->nullable();
            $table->timestamp('verification_sent_at')->nullable();
            $table->unsignedSmallInteger('verification_attempts')->default(0);
            $table->string('signature_type')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('geolocation')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'document_id']);
            $table->index(['document_id', 'order']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_signers');
    }
};
