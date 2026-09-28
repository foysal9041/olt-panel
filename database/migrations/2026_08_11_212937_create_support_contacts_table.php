<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('vendor_name');
            $table->string('contact_person')->nullable();
            $table->string('phone');
            $table->string('email')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['category', 'vendor_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_contacts');
    }
};
