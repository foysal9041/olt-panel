<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('default_days_per_year')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('leave_types')->insert([
            ['name' => 'Casual Leave', 'default_days_per_year' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sick Leave', 'default_days_per_year' => 14, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Earned Leave', 'default_days_per_year' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Unpaid Leave', 'default_days_per_year' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
