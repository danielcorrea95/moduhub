<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_signature_fields', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignUlid('signer_id')->nullable()->constrained('document_signers')->nullOnDelete();
            $table->string('type')->default('signature');
            $table->unsignedSmallInteger('page')->default(1);
            $table->decimal('x', 8, 2);
            $table->decimal('y', 8, 2);
            $table->decimal('width', 8, 2);
            $table->decimal('height', 8, 2);
            $table->boolean('required')->default(true);
            $table->text('value')->nullable();
            $table->timestamps();
            $table->index(['document_id', 'page']);
            $table->index(['document_id', 'signer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_signature_fields');
    }
};
