<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('switch_ports', function (Blueprint $table) {
            // Rx power limits reported by the transceiver itself (dBm).
            $table->float('rx_high_alarm')->nullable()->after('rx_power');
            $table->float('rx_high_warn')->nullable()->after('rx_high_alarm');
            $table->float('rx_low_warn')->nullable()->after('rx_high_warn');
            $table->float('rx_low_alarm')->nullable()->after('rx_low_warn');
        });
    }

    public function down(): void
    {
        Schema::table('switch_ports', function (Blueprint $table) {
            $table->dropColumn(['rx_high_alarm', 'rx_high_warn', 'rx_low_warn', 'rx_low_alarm']);
        });
    }
};
