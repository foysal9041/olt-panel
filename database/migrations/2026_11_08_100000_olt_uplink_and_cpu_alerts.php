<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * OLT uplink alerts (the switch port an OLT hangs off) and CPU alerts
     * (total or busiest core of a router / switch).
     */
    public function up(): void
    {
        Schema::table('olts', function (Blueprint $table) {
            $table->foreignId('uplink_port_id')->nullable()->after('snmp')->constrained('switch_ports')->nullOnDelete();
        });

        Schema::table('network_switches', function (Blueprint $table) {
            $table->unsignedTinyInteger('cpu_core_max')->nullable()->after('cpu_percent');
            $table->unsignedSmallInteger('cpu_cores')->nullable()->after('cpu_core_max');
            $table->unsignedTinyInteger('cpu_streak')->default(0)->after('cpu_cores');
            $table->boolean('cpu_alarm')->default(false)->after('cpu_streak');
        });

        Schema::table('noc_alert_settings', function (Blueprint $table) {
            $table->boolean('alert_olt_uplink')->default(true)->after('alert_port_status');
            $table->boolean('alert_cpu')->default(true)->after('util_minutes');
            $table->unsignedTinyInteger('cpu_threshold')->default(70)->after('alert_cpu');
            $table->unsignedTinyInteger('cpu_minutes')->default(3)->after('cpu_threshold');
            $table->boolean('cpu_switches')->default(false)->after('cpu_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('olts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uplink_port_id');
        });
        Schema::table('network_switches', function (Blueprint $table) {
            $table->dropColumn(['cpu_core_max', 'cpu_cores', 'cpu_streak', 'cpu_alarm']);
        });
        Schema::table('noc_alert_settings', function (Blueprint $table) {
            $table->dropColumn(['alert_olt_uplink', 'alert_cpu', 'cpu_threshold', 'cpu_minutes', 'cpu_switches']);
        });
    }
};
