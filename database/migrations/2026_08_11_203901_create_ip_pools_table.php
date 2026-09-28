<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_pools', function (Blueprint $table) {
            $table->id();
            $table->string('subnet')->unique();
            $table->string('gateway')->nullable();
            $table->string('type')->default('public');
            $table->string('zone')->nullable();
            $table->string('vlan')->nullable();
            $table->string('status')->default('active');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_pools');
    }
};
