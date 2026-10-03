<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Office ID cards issued to anyone — employees or not — each with its
     * own number, issue and expiry date, so the QR on the card can say
     * whether that card is still valid.
     */
    public function up(): void
    {
        Schema::create('id_cards', function (Blueprint $table) {
            $table->id();
            $table->string('card_no')->unique();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('designation')->nullable();
            $table->string('department')->nullable();
            $table->string('id_no')->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->string('phone', 50)->nullable();
            $table->date('joining_date')->nullable();
            $table->date('issue_date');
            $table->date('expiry_date')->nullable();
            $table->string('photo')->nullable();
            $table->string('theme', 20)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('id_cards');
    }
};
