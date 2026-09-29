<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Cash on hand" counted at the start of a day. The latest one on or
        // before a date is where the cash book's জের starts counting from.
        Schema::create('cash_openings', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->decimal('amount', 14, 2);
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_openings');
    }
};
