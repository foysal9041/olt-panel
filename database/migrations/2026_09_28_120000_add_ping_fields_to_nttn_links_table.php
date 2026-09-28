<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nttn_links', function (Blueprint $table) {
            // Optional override; blank = ping the peering IP.
            $table->string('ping_ip', 45)->nullable()->after('peering_ip');

            // Result of the last on-demand ping.
            $table->timestamp('last_ping_at')->nullable()->after('remarks');
            $table->boolean('last_ping_ok')->nullable()->after('last_ping_at');
            $table->float('last_ping_rtt')->nullable()->after('last_ping_ok');
            $table->float('last_ping_loss')->nullable()->after('last_ping_rtt');
        });
    }

    public function down(): void
    {
        Schema::table('nttn_links', function (Blueprint $table) {
            $table->dropColumn(['ping_ip', 'last_ping_at', 'last_ping_ok', 'last_ping_rtt', 'last_ping_loss']);
        });
    }
};
