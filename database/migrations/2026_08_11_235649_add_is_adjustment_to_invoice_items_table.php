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
        Schema::table('invoice_items', function (Blueprint $table) {
            // Distinguishes auto-generated billing lines (bandwidth
            // breakdown) from manual adjustments staff add by hand — e.g. a
            // mid-month bandwidth upgrade/downgrade credit or charge.
            $table->boolean('is_adjustment')->default(false)->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('is_adjustment');
        });
    }
};
