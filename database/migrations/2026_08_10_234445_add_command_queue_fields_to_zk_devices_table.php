<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('zk_devices', function (Blueprint $table) {
            $table->string('zone')->nullable()->after('name');
            $table->text('pending_command')->nullable()->after('last_seen_at');
            $table->string('pending_command_id')->nullable()->after('pending_command');
            $table->timestamp('command_issued_at')->nullable()->after('pending_command_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zk_devices', function (Blueprint $table) {
            $table->dropColumn(['zone', 'pending_command', 'pending_command_id', 'command_issued_at']);
        });
    }
};
