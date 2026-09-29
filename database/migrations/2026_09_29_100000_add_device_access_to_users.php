<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which OLTs / switches a user may see: all, their zone, or a
        // hand-picked list (the pivot tables below). Admins always see all.
        Schema::table('users', function (Blueprint $table) {
            $table->string('olt_access', 10)->default('zone')->after('zone');
            $table->string('switch_access', 10)->default('zone')->after('olt_access');
        });

        Schema::create('olt_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('olt_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'olt_id']);
        });

        Schema::create('network_switch_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('network_switch_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'network_switch_id']);
        });

        // Keep today's behaviour: admin/noc saw every OLT, others their zone.
        DB::table('users')->whereIn(DB::raw('LOWER(role)'), ['admin', 'noc'])
            ->update(['olt_access' => 'all', 'switch_access' => 'all']);
    }

    public function down(): void
    {
        Schema::dropIfExists('network_switch_user');
        Schema::dropIfExists('olt_user');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['olt_access', 'switch_access']);
        });
    }
};
