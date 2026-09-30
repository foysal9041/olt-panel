<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Zone / partner settlements imported from an Excel sheet of each zone's
     * Total Payment and (negative) Deduction for a month.
     */
    public function up(): void
    {
        Schema::create('zone_settlements', function (Blueprint $table) {
            $table->id();
            $table->date('month');                                  // 1st of the billing month
            $table->date('invoice_date');
            $table->decimal('bkash_percent', 6, 3)->default(1.5);
            $table->string('source_name');                          // uploaded file name
            $table->string('source_path')->nullable();              // stored copy (storage/app/…)
            $table->string('sheet_name')->nullable();
            $table->json('mapping')->nullable();                    // header row + columns used
            $table->json('original')->nullable();                   // the sheet as read (for "Original Data")
            $table->boolean('blank_deduction_as_zero')->default(false);
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('zone_settlement_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_settlement_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('source_row');                  // Excel row number
            $table->string('name')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('total_payment', 18, 6)->nullable();    // exactly as in the sheet
            $table->decimal('deduction', 18, 6)->nullable();        // negative, sign kept
            $table->boolean('included')->default(true);             // counted in totals / invoiced
            $table->json('flags')->nullable();                      // what to check on this row
            $table->string('invoice_no', 40)->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zone_settlement_rows');
        Schema::dropIfExists('zone_settlements');
    }
};
