<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rx warning level by link speed; these win over module limits.
        Schema::table('noc_alert_settings', function (Blueprint $table) {
            $table->float('rx_warn_10g')->nullable()->default(-15)->after('rx_low_threshold');
            $table->float('rx_warn_1g')->nullable()->default(-18)->after('rx_warn_10g');
        });
    }

    public function down(): void
    {
        Schema::table('noc_alert_settings', function (Blueprint $table) {
            $table->dropColumn(['rx_warn_10g', 'rx_warn_1g']);
        });
    }
};
