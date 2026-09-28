<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * OLT model is no longer asked for or shown. The column stays (existing
     * values are kept) but must allow NULL so new OLTs can be saved.
     */
    public function up(): void
    {
        Schema::table('olts', function (Blueprint $table) {
            $table->string('model')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('olts', function (Blueprint $table) {
            $table->string('model')->nullable(false)->change();
        });
    }
};
