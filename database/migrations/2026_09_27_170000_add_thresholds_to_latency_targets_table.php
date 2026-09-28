<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('latency_targets', function (Blueprint $table) {
            $table->float('latency_threshold')->nullable()->after('pings');
            $table->float('loss_threshold')->nullable()->after('latency_threshold');
            $table->boolean('notify')->default(true)->after('loss_threshold');

            // Alert state: flips only after ALERT_AFTER probes in a row
            // disagree with it, so one spike doesn't page anyone.
            $table->boolean('alert_active')->default(false)->after('last_loss');
            $table->unsignedTinyInteger('alert_streak')->default(0)->after('alert_active');
            $table->timestamp('alert_since')->nullable()->after('alert_streak');
        });
    }

    public function down(): void
    {
        Schema::table('latency_targets', function (Blueprint $table) {
            $table->dropColumn(['latency_threshold', 'loss_threshold', 'notify', 'alert_active', 'alert_streak', 'alert_since']);
        });
    }
};
