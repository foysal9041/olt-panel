<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('olts', function (Blueprint $table) {

            $table->id();

            $table->string('zone');
            $table->string('brand');
            $table->string('model');
            $table->string('vlan', 100)->nullable();

            $table->string('name');

            $table->string('ip')->unique();

            $table->string('username');

            $table->text('password');

            $table->string('snmp')->nullable();

            $table->timestamps();

            // Reachable at the last check (app:check-olt-status).
            $table->boolean('status')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('olts');
    }
};


