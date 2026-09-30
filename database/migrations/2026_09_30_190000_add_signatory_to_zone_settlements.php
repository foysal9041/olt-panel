<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who signs the customer invoices ("Prepared By").
     */
    public function up(): void
    {
        Schema::table('zone_settlements', function (Blueprint $table) {
            $table->string('prepared_by', 100)->default('Md Kamruzzaman');
            $table->string('prepared_title', 100)->default('Manager');
        });
    }

    public function down(): void
    {
        Schema::table('zone_settlements', function (Blueprint $table) {
            $table->dropColumn(['prepared_by', 'prepared_title']);
        });
    }
};
