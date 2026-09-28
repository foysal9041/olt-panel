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
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['duty_shift_id']);
            $table->dropUnique('users_device_user_id_unique');
            $table->dropColumn(['device_user_id', 'designation', 'duty_shift_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('device_user_id')->nullable()->unique()->after('zone');
            $table->string('designation')->nullable()->after('device_user_id');
            $table->foreignId('duty_shift_id')->nullable()->after('designation')
                ->constrained('duty_shifts')->nullOnDelete();
        });
    }
};
