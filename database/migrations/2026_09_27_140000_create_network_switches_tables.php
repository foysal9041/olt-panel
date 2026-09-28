<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_switches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ip')->unique();
            $table->string('zone')->nullable();
            $table->string('vendor', 30)->default('generic');
            $table->string('snmp_version', 3)->default('2c');
            $table->text('community');
            $table->unsignedSmallInteger('snmp_port')->default(161);
            $table->boolean('is_active')->default(true);
            $table->boolean('notify')->default(true);

            // Optional per-switch transceiver OIDs (indexed by ifIndex) for
            // models whose DOM MIB isn't built in, e.g. some BDCOM/DCN.
            $table->string('dom_rx_oid')->nullable();
            $table->string('dom_tx_oid')->nullable();
            $table->string('dom_temp_oid')->nullable();
            $table->unsignedInteger('dom_divisor')->default(1);
            $table->string('dom_power_unit', 4)->default('dbm');

            // Poll state
            $table->tinyInteger('status')->nullable();
            $table->unsignedTinyInteger('fail_count')->default(0);
            $table->string('sys_name')->nullable();
            $table->text('sys_descr')->nullable();
            $table->unsignedBigInteger('uptime_seconds')->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();
        });

        Schema::create('switch_ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_switch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('if_index');
            $table->string('name')->nullable();
            $table->string('descr')->nullable();
            $table->string('alias')->nullable();
            $table->unsignedSmallInteger('if_type')->nullable();
            $table->unsignedTinyInteger('admin_status')->nullable();
            $table->unsignedTinyInteger('oper_status')->nullable();
            $table->unsignedInteger('speed_mbps')->nullable();
            $table->timestamp('last_change_at')->nullable();
            $table->boolean('notify')->default(true);

            // Transceiver (DOM) readings
            $table->float('rx_power')->nullable();
            $table->float('tx_power')->nullable();
            $table->float('temperature')->nullable();
            $table->float('voltage')->nullable();
            $table->float('bias')->nullable();
            $table->boolean('rx_alarm')->default(false);
            $table->timestamp('dom_updated_at')->nullable();

            $table->timestamps();

            $table->unique(['network_switch_id', 'if_index']);
        });

        Schema::create('switch_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_switch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('switch_port_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('message');
            $table->boolean('notified')->default(false);
            $table->timestamp('occurred_at')->index();
        });

        Schema::create('noc_alert_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('telegram_enabled')->default(false);
            $table->text('telegram_bot_token')->nullable();
            $table->string('telegram_chat_ids')->nullable();
            $table->boolean('alert_port_status')->default(true);
            $table->boolean('alert_switch_status')->default(true);
            $table->float('rx_low_threshold')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('noc_alert_settings');
        Schema::dropIfExists('switch_events');
        Schema::dropIfExists('switch_ports');
        Schema::dropIfExists('network_switches');
    }
};
