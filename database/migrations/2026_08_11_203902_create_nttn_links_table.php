<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nttn_links', function (Blueprint $table) {
            $table->id();
            $table->string('link_id')->unique();
            $table->string('provider')->nullable();
            $table->string('address');
            $table->string('bandwidth');
            $table->string('location');
            $table->string('zone')->nullable();
            $table->string('status')->default('active');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nttn_links');
    }
};
