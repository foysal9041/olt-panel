<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The customer's username in the billing / partner system
     * (e.g. "strkamrul"), so a client can be found by it.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('username', 100)->nullable()->after('name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['username']);
            $table->dropColumn('username');
        });
    }
};
