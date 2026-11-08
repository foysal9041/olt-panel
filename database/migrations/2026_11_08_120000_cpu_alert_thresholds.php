<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CPU alert levels: total and single core separately (Settings → Telegram),
     * and optionally each device's own (its edit page; empty = the settings').
     */
    public function up(): void
    {
        Schema::table('noc_alert_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('cpu_core_threshold')->default(70)->after('cpu_threshold');
        });

        Schema::table('network_switches', function (Blueprint $table) {
            $table->unsignedTinyInteger('cpu_alert_total')->nullable()->after('cpu_alarm');
            $table->unsignedTinyInteger('cpu_alert_core')->nullable()->after('cpu_alert_total');
        });
    }

    public function down(): void
    {
        Schema::table('noc_alert_settings', function (Blueprint $table) {
            $table->dropColumn('cpu_core_threshold');
        });
        Schema::table('network_switches', function (Blueprint $table) {
            $table->dropColumn(['cpu_alert_total', 'cpu_alert_core']);
        });
    }
};
