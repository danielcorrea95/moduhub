<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('status')->default('draft');
            $table->string('file_disk')->default('documents');
            $table->string('file_path');
            $table->string('file_hash', 64);
            $table->unsignedInteger('file_size');
            $table->unsignedSmallInteger('page_count')->default(0);
            $table->string('final_file_path')->nullable();
            $table->string('final_file_hash', 64)->nullable();
            $table->string('sign_order')->default('parallel');
            $table->text('message')->nullable();
            $table->string('external_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
