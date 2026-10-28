<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a duty shift ends, so the attendance report can show extra duty
 * (time after it) next to late arrival. An end before the start means
 * the shift runs past midnight.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('duty_shifts', function (Blueprint $table) {
            $table->time('end_time')->nullable()->after('start_time');
        });
    }

    public function down(): void
    {
        Schema::table('duty_shifts', fn (Blueprint $table) => $table->dropColumn('end_time'));
    }
};
