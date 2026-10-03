<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - The money is kept in the bank; the office keeps some cash for its
     *   expenses — that office cash is "cash on hand". Every entry now says
     *   which it moved: office cash (the Cash Book) or the bank.
     * - Payments from bandwidth clients record who received them and a
     *   reference (bank / bKash transaction id), for the money receipt.
     * - "জমা" in the Cash Book is cash brought into the office (from the
     *   bank), not income: it no longer counts on the Net Profit sheet.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('account', 10)->default('cash')->after('transaction_category_id');
            $table->index(['account', 'transaction_date']);
        });

        Schema::table('bandwidth_payments', function (Blueprint $table) {
            $table->string('received_by', 100)->nullable()->after('method');
            $table->string('reference', 100)->nullable()->after('received_by');
        });

        DB::table('transaction_categories')->where('type', 'income')->where('name', 'জমা')->update(['pl_group' => 'none']);
    }

    public function down(): void
    {
        Schema::table('bandwidth_payments', function (Blueprint $table) {
            $table->dropColumn(['received_by', 'reference']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['account', 'transaction_date']);
            $table->dropColumn('account');
        });
    }
};
