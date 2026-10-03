<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The office gives every client the same number for a month
     * ("Sunlit/DC/JULY"), so a number only has to be unique per client.
     * Numbers made here (Sunlit/DC/OCT26/01, …) stay unique anyway.
     */
    public function up(): void
    {
        Schema::table('bandwidth_invoices', function (Blueprint $table) {
            $table->dropUnique(['invoice_no']);
            $table->unique(['customer_id', 'invoice_no']);
        });
    }

    public function down(): void
    {
        Schema::table('bandwidth_invoices', function (Blueprint $table) {
            $table->dropUnique(['customer_id', 'invoice_no']);
            $table->unique('invoice_no');
        });
    }
};
