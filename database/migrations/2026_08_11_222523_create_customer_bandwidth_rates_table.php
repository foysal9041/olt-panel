<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_bandwidth_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('bandwidth_type_id')->constrained('bandwidth_types')->cascadeOnDelete();
            $table->decimal('rate', 10, 2);
            $table->timestamps();

            $table->unique(['customer_id', 'bandwidth_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_bandwidth_rates');
    }
};
