<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // mac_client: billed on a single package (product_id + package_rate).
            // bandwidth_client: a reseller billed per bandwidth type instead.
            $table->string('customer_type')->default('mac_client')->after('product_id');

            $table->decimal('package_rate', 10, 2)->nullable()->after('customer_type');

            $table->decimal('iig_rate', 10, 2)->nullable()->after('package_rate');
            $table->decimal('ggc_rate', 10, 2)->nullable()->after('iig_rate');
            $table->decimal('fna_rate', 10, 2)->nullable()->after('ggc_rate');
            $table->decimal('bdix_rate', 10, 2)->nullable()->after('fna_rate');
            $table->decimal('cdn_rate', 10, 2)->nullable()->after('bdix_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'customer_type',
                'package_rate',
                'iig_rate',
                'ggc_rate',
                'fna_rate',
                'bdix_rate',
                'cdn_rate',
            ]);
        });
    }
};
