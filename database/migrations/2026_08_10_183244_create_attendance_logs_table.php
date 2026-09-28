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
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zk_device_id')->constrained('zk_devices')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('device_user_id');
            $table->dateTime('punched_at');
            $table->unsignedTinyInteger('verify_mode')->nullable();
            $table->unsignedTinyInteger('status_code')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(
                ['zk_device_id', 'device_user_id', 'punched_at'],
                'attendance_logs_unique_punch'
            );

            $table->index(['user_id', 'punched_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
