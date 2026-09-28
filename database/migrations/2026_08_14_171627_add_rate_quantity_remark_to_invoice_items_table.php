<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            // Rate/quantity are set for billing lines that come from a
            // rate × quantity calculation (bandwidth types, packages) so
            // the printed invoice can show the breakdown, not just the
            // line total. Null for lines where a single rate/qty doesn't
            // apply.
            $table->decimal('rate', 12, 2)->nullable()->after('amount');
            $table->decimal('quantity', 10, 2)->nullable()->after('rate');

            // Free-text note staff can attach to a manual adjustment (e.g.
            // "customer requested downgrade mid-month").
            $table->string('remark')->nullable()->after('is_adjustment');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['rate', 'quantity', 'remark']);
        });
    }
};
