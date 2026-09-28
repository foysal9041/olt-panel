<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nttn_links', function (Blueprint $table) {
            // Pinged every minute by app:check-nttn-links when on.
            $table->boolean('monitor')->default(true)->after('ping_ip');

            // Confirmed state: 1 up, 0 down, null never checked. It flips
            // only after STATE_AFTER checks in a row disagree with it.
            $table->tinyInteger('link_state')->nullable()->after('last_ping_loss');
            $table->unsignedTinyInteger('state_streak')->default(0)->after('link_state');
            $table->timestamp('state_changed_at')->nullable()->after('state_streak');
        });

        Schema::table('noc_alert_settings', function (Blueprint $table) {
            $table->boolean('alert_nttn_status')->default(true)->after('alert_switch_status');
        });
    }

    public function down(): void
    {
        Schema::table('nttn_links', function (Blueprint $table) {
            $table->dropColumn(['monitor', 'link_state', 'state_streak', 'state_changed_at']);
        });

        Schema::table('noc_alert_settings', function (Blueprint $table) {
            $table->dropColumn('alert_nttn_status');
        });
    }
};
