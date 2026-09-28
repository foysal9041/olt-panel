<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per SFP port per poll (every minute): the Rx/Tx history.
        Schema::create('switch_port_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('switch_port_id')->constrained()->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->float('rx_power')->nullable();
            $table->float('tx_power')->nullable();
            $table->float('temperature')->nullable();

            $table->index(['switch_port_id', 'recorded_at']);
            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('switch_port_readings');
    }
};
