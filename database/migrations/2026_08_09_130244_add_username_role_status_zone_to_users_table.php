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
        // The first install got these columns by hand; a fresh install gets them here.
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username', 50)->nullable()->after('email')->unique('username');
            }
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 50)->nullable()->default('viewer');
            }
            if (! Schema::hasColumn('users', 'status')) {
                $table->boolean('status')->nullable()->default(true);
            }
            if (! Schema::hasColumn('users', 'zone')) {
                $table->string('zone', 100)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('username');
            $table->dropColumn(['username', 'role', 'status', 'zone']);
        });
    }
};
