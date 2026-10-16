<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->string('signature_image_path')->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->dropColumn('signature_image_path');
        });
    }
};
