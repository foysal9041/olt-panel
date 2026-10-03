<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR documents: what an ID card and the appointment / termination
     * letters need about an employee, the letters themselves, and a small
     * key-value store for settings (ID card back side, signatory, signature).
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('name');
            $table->string('department')->nullable()->after('designation');
            $table->date('joining_date')->nullable()->after('department');
            $table->string('blood_group', 5)->nullable()->after('joining_date');
            $table->string('email')->nullable()->after('phone');
            $table->text('address')->nullable()->after('email');
            $table->string('nid', 30)->nullable()->after('address');
            $table->date('left_on')->nullable()->after('status');
        });

        Schema::create('hr_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('ref_no')->unique();
            $table->date('letter_date');
            $table->json('data');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['type', 'letter_date']);
        });

        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('hr_letters');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['photo', 'department', 'joining_date', 'blood_group', 'email', 'address', 'nid', 'left_on']);
        });
    }
};
