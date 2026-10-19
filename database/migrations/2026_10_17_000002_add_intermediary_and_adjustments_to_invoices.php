<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('intermediary')->nullable()->after('taker');
            $table->decimal('unconditional_discount', 15, 2)->nullable()->after('amount');
            $table->decimal('conditional_discount', 15, 2)->nullable()->after('unconditional_discount');
            $table->decimal('deduction_amount', 15, 2)->nullable()->after('conditional_discount');
            $table->decimal('deduction_percent', 5, 2)->nullable()->after('deduction_amount');
            $table->string('iss_withholder', 20)->nullable()->after('iss_withheld');
            $table->unsignedTinyInteger('immunity_type')->nullable()->after('iss_taxation');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'intermediary', 'unconditional_discount', 'conditional_discount',
                'deduction_amount', 'deduction_percent', 'iss_withholder', 'immunity_type',
            ]);
        });
    }
};
