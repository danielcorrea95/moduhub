<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->timestamp('sealed_at')->nullable()->after('completed_at');
            $table->string('seal_certificate_subject')->nullable()->after('sealed_at');
            $table->string('seal_certificate_issuer')->nullable()->after('seal_certificate_subject');
            $table->string('seal_certificate_serial', 128)->nullable()->after('seal_certificate_issuer');
            $table->timestamp('seal_certificate_valid_to')->nullable()->after('seal_certificate_serial');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn([
                'sealed_at',
                'seal_certificate_subject',
                'seal_certificate_issuer',
                'seal_certificate_serial',
                'seal_certificate_valid_to',
            ]);
        });
    }
};
