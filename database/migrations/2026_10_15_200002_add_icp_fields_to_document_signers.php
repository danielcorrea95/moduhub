<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->string('icp_certificate_subject')->nullable()->after('signature_image_path');
            $table->string('icp_certificate_serial')->nullable()->after('icp_certificate_subject');
            $table->string('icp_certificate_issuer')->nullable()->after('icp_certificate_serial');
            $table->timestamp('icp_certificate_valid_from')->nullable()->after('icp_certificate_issuer');
            $table->timestamp('icp_certificate_valid_to')->nullable()->after('icp_certificate_valid_from');
        });
    }

    public function down(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->dropColumn([
                'icp_certificate_subject',
                'icp_certificate_serial',
                'icp_certificate_issuer',
                'icp_certificate_valid_from',
                'icp_certificate_valid_to',
            ]);
        });
    }
};
