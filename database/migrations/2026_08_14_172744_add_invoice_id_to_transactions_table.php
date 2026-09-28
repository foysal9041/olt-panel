<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets one invoice have many payments (transactions), for customers
     * who pay an invoice off in installments instead of all at once.
     * invoices.transaction_id (single, legacy) is backfilled into the new
     * column below so existing paid invoices keep their payment link.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        DB::statement('
            UPDATE transactions t
            INNER JOIN invoices i ON i.transaction_id = t.id
            SET t.invoice_id = i.id
            WHERE t.invoice_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
        });
    }
};
