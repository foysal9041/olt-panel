<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Office time: only punches between these count for attendance
     * (both empty = the whole day). An end at or before the start runs
     * past midnight, and those after-midnight punches belong to the day before.
     */
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->time('punch_from')->nullable()->after('weekly_off_day');
            $table->time('punch_until')->nullable()->after('punch_from');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn(['punch_from', 'punch_until']);
        });
    }
};
