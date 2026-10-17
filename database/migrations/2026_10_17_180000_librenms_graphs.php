<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LibreNMS-style graphs: per-port packets (unicast / non-unicast), errors
 * and discards next to the bits; device memory, uptime and SNMP response
 * time; and hardware sensors (temperature, fans, voltage, power) — all kept
 * in the same 1-minute / 30-minute / 2-hour / 1-day buckets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('switch_port_traffic', function (Blueprint $table) {
            // Packets per second; errors and discards in thousandths per second.
            foreach (['pkt_in_sum', 'pkt_out_sum', 'pkt_in_max', 'pkt_out_max', 'npkt_in_sum', 'npkt_out_sum', 'err_in_sum', 'err_out_sum', 'disc_in_sum', 'disc_out_sum'] as $col) {
                $table->unsignedBigInteger($col)->default(0);
            }
            $table->unsignedSmallInteger('pkt_samples')->default(0);
        });

        Schema::table('switch_ports', function (Blueprint $table) {
            $table->json('pkt_counters')->nullable()->after('traffic_at');
            $table->json('pkt_rates')->nullable()->after('pkt_counters');
        });

        Schema::table('network_switches', function (Blueprint $table) {
            $table->unsignedTinyInteger('mem_percent')->nullable()->after('cpu_at');
            $table->string('mem_source', 10)->nullable()->after('mem_percent');
            $table->unsignedBigInteger('mem_total_bytes')->nullable()->after('mem_source');
            $table->unsignedInteger('snmp_ms')->nullable()->after('mem_total_bytes');
            $table->timestamp('sensors_at')->nullable()->after('snmp_ms');
        });

        Schema::create('device_metrics', function (Blueprint $table) {
            $table->foreignId('network_switch_id')->constrained()->cascadeOnDelete();
            $table->string('metric', 30);
            $table->unsignedSmallInteger('res');
            $table->unsignedInteger('at');
            $table->bigInteger('value_sum')->default(0);
            $table->bigInteger('value_max')->default(0);
            $table->unsignedSmallInteger('samples')->default(0);
            $table->primary(['network_switch_id', 'metric', 'res', 'at']);
            $table->index(['res', 'at']);
        });

        Schema::create('device_sensors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_switch_id')->constrained()->cascadeOnDelete();
            $table->string('source', 12);
            $table->string('sensor_index', 64);
            $table->string('class', 20);
            $table->string('name', 120);
            $table->string('unit', 10);
            $table->string('value_oid', 120);
            $table->double('multiplier')->default(1);
            $table->double('value')->nullable();
            $table->timestamp('value_at')->nullable();
            $table->timestamps();
            $table->unique(['network_switch_id', 'source', 'sensor_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_sensors');
        Schema::dropIfExists('device_metrics');
        Schema::table('network_switches', fn (Blueprint $t) => $t->dropColumn(['mem_percent', 'mem_source', 'mem_total_bytes', 'snmp_ms', 'sensors_at']));
        Schema::table('switch_ports', fn (Blueprint $t) => $t->dropColumn(['pkt_counters', 'pkt_rates']));
        Schema::table('switch_port_traffic', fn (Blueprint $t) => $t->dropColumn(['pkt_in_sum', 'pkt_out_sum', 'pkt_in_max', 'pkt_out_max', 'npkt_in_sum', 'npkt_out_sum', 'err_in_sum', 'err_out_sum', 'disc_in_sum', 'disc_out_sum', 'pkt_samples']));
    }
};
