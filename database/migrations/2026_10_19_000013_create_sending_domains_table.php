<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domínio próprio do workspace para os e-mails que ele manda a signatários e tomadores. Um por
 * workspace, e um domínio só pode pertencer a um workspace (a identidade no SES é da conta toda).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sending_domains', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->string('status', 16);
            $table->json('dkim_tokens');
            $table->string('dkim_status', 24)->nullable();
            $table->string('mail_from_status', 24)->nullable();
            $table->string('from_name', 100);
            $table->string('from_local_part', 64);
            $table->string('reply_to')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sending_domains');
    }
};
