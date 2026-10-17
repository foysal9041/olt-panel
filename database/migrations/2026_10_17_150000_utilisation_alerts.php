<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alert when a port's traffic stays at or above a share of its speed
 * (default 85% for 3 minutes); each port remembers its alarm and how
 * many polls in a row it has been over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('noc_alert_settings', function (Blueprint $table) {
            $table->boolean('alert_util')->default(true);
            $table->unsignedTinyInteger('util_threshold')->default(85);
            $table->unsignedTinyInteger('util_minutes')->default(3);
        });

        Schema::table('switch_ports', function (Blueprint $table) {
            $table->boolean('util_alarm')->default(false)->after('traffic_at');
            $table->unsignedTinyInteger('util_streak')->default(0)->after('util_alarm');
        });
    }

    public function down(): void
    {
        Schema::table('noc_alert_settings', fn (Blueprint $table) => $table->dropColumn(['alert_util', 'util_threshold', 'util_minutes']));
        Schema::table('switch_ports', fn (Blueprint $table) => $table->dropColumn(['util_alarm', 'util_streak']));
    }
};
