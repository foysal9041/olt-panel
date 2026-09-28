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
        Schema::table('customer_bandwidth_rates', function (Blueprint $table) {
            // rate is now the per-unit (per Mbps) price; quantity is how
            // many Mbps the customer is subscribed to for that type.
            $table->decimal('quantity', 10, 2)->default(1)->after('rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_bandwidth_rates', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
