<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Routers are polled exactly like switches (SNMP: interfaces, status,
 * traffic graphs); device_type tells them apart in lists and pages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_switches', function (Blueprint $table) {
            $table->string('device_type', 10)->default('switch')->after('name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('network_switches', fn (Blueprint $table) => $table->dropColumn('device_type'));
    }
};
