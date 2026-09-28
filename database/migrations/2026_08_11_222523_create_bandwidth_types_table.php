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
        Schema::create('bandwidth_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // Seed with the types already in use so nothing regresses for
        // existing Bandwidth Client customers.
        $now = now();

        DB::table('bandwidth_types')->insert(
            collect(['IIG', 'GGC', 'FNA', 'BDIX', 'CDN'])->map(fn ($name) => [
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bandwidth_types');
    }
};
