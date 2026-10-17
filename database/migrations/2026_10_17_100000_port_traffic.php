<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cacti-style traffic graphs for switch ports: each port keeps its last
 * octet counters (to work out bits per second on the next poll) and its
 * current rate; switch_port_traffic holds the rates at 1-minute, 30-minute,
 * 2-hour and 1-day resolution (sum + max + sample count per bucket).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('switch_ports', function (Blueprint $table) {
            $table->unsignedBigInteger('in_octets')->nullable()->after('dom_updated_at');
            $table->unsignedBigInteger('out_octets')->nullable()->after('in_octets');
            $table->unsignedBigInteger('octets_at')->nullable()->after('out_octets')->comment('epoch ms of the counters');
            $table->unsignedTinyInteger('counter_bits')->nullable()->after('octets_at');
            $table->unsignedBigInteger('in_bps')->nullable()->after('counter_bits');
            $table->unsignedBigInteger('out_bps')->nullable()->after('in_bps');
            $table->timestamp('traffic_at')->nullable()->after('out_bps');
        });

        Schema::create('switch_port_traffic', function (Blueprint $table) {
            $table->foreignId('switch_port_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('res')->comment('bucket size in minutes');
            $table->unsignedInteger('at')->comment('bucket start, epoch seconds');
            $table->unsignedBigInteger('in_sum')->default(0);
            $table->unsignedBigInteger('out_sum')->default(0);
            $table->unsignedBigInteger('in_max')->default(0);
            $table->unsignedBigInteger('out_max')->default(0);
            $table->unsignedSmallInteger('samples')->default(0);

            $table->primary(['switch_port_id', 'res', 'at']);
            $table->index(['res', 'at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('switch_port_traffic');
        Schema::table('switch_ports', fn (Blueprint $table) => $table->dropColumn(['in_octets', 'out_octets', 'octets_at', 'counter_bits', 'in_bps', 'out_bps', 'traffic_at']));
    }
};
