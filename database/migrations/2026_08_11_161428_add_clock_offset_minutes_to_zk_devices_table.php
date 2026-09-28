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
        Schema::table('zk_devices', function (Blueprint $table) {
            $table->integer('clock_offset_minutes')->default(0)->after('zone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zk_devices', function (Blueprint $table) {
            $table->dropColumn('clock_offset_minutes');
        });
    }
};
