<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guards against entering the same thing twice:
     * - a settlement remembers its file's fingerprint, so the same Excel
     *   can't be saved again;
     * - a bandwidth payment's reference (bank / bKash transaction id) is
     *   unique per client;
     * - a client has one rate row per type per start date;
     * - customer usernames are unique.
     */
    public function up(): void
    {
        Schema::table('zone_settlements', function (Blueprint $table) {
            $table->string('file_hash', 64)->nullable()->after('source_path');
            $table->index('file_hash');
        });

        Schema::table('bandwidth_payments', function (Blueprint $table) {
            $table->unique(['customer_id', 'reference']);
        });

        Schema::table('bandwidth_service_changes', function (Blueprint $table) {
            $table->unique(['customer_id', 'bandwidth_type_id', 'effective_from'], 'bw_changes_one_per_day');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->unique('username');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['username']);
        });
        Schema::table('bandwidth_service_changes', function (Blueprint $table) {
            $table->dropUnique('bw_changes_one_per_day');
        });
        Schema::table('bandwidth_payments', function (Blueprint $table) {
            $table->dropUnique(['customer_id', 'reference']);
        });
        Schema::table('zone_settlements', function (Blueprint $table) {
            $table->dropIndex(['file_hash']);
            $table->dropColumn('file_hash');
        });
    }
};
