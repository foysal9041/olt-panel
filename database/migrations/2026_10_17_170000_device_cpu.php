<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CPU load of switches and routers (SNMP): the current value and which
 * MIB answered on the device, and the history in the same 1-minute /
 * 30-minute / 2-hour / 1-day buckets as port traffic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_switches', function (Blueprint $table) {
            $table->unsignedTinyInteger('cpu_percent')->nullable()->after('uptime_seconds');
            $table->string('cpu_source', 10)->nullable()->after('cpu_percent');
            $table->timestamp('cpu_at')->nullable()->after('cpu_source');
        });

        Schema::create('device_cpu', function (Blueprint $table) {
            $table->foreignId('network_switch_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('res');
            $table->unsignedInteger('at');
            $table->unsignedInteger('cpu_sum')->default(0);
            $table->unsignedTinyInteger('cpu_max')->default(0);
            $table->unsignedSmallInteger('samples')->default(0);
            $table->primary(['network_switch_id', 'res', 'at']);
            $table->index(['res', 'at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_cpu');
        Schema::table('network_switches', fn (Blueprint $table) => $table->dropColumn(['cpu_percent', 'cpu_source', 'cpu_at']));
    }
};
