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
            // Key Account Manager — mainly relevant for Bandwidth Clients.
            $table->string('kam_name')->nullable()->after('package_rate');
            $table->string('kam_phone')->nullable()->after('kam_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['kam_name', 'kam_phone']);
        });
    }
};
