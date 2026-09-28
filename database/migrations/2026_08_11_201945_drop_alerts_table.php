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
        Schema::dropIfExists('alerts');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('alerts', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('olt_id')->nullable();

            $table->string('severity');
            $table->text('message');

            $table->timestamps();

        });
    }
};
