<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** How tall a dashboard graph is drawn, in pixels (null = the default). */
    public function up(): void
    {
        Schema::table('graph_widgets', function (Blueprint $table) {
            $table->unsignedSmallInteger('height')->nullable()->after('width');
        });
    }

    public function down(): void
    {
        Schema::table('graph_widgets', function (Blueprint $table) {
            $table->dropColumn('height');
        });
    }
};
