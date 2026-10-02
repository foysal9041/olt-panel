<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A settlement's Net Bill (company income) can be posted to Accounts as
     * one income entry per zone; each row remembers its entry so the posting
     * can be undone.
     */
    public function up(): void
    {
        Schema::table('zone_settlements', function (Blueprint $table) {
            $table->timestamp('posted_at')->nullable();
            $table->date('posted_on')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('zone_settlement_rows', function (Blueprint $table) {
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('zone_settlement_rows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transaction_id');
        });

        Schema::table('zone_settlements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('posted_by');
            $table->dropColumn(['posted_at', 'posted_on']);
        });
    }
};
