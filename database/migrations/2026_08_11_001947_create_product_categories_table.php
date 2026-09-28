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
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // Starter categories for a Bangladeshi ISP — admins can add more
        // from the Products page at any time.
        $now = now();

        DB::table('product_categories')->insert(
            collect(['INT', 'GGC', 'FNA', 'CDN', 'BDIX', 'VAS', 'SFP', 'Patch Cord', 'Internet Goods'])
                ->map(fn ($name) => ['name' => $name, 'created_at' => $now, 'updated_at' => $now])
                ->all()
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
