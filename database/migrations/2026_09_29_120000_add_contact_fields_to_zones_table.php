<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('name');
            $table->string('username')->nullable()->after('code');
            $table->string('contact_name')->nullable()->after('username');
            $table->string('phone')->nullable()->after('contact_name');
            $table->string('email')->nullable()->after('phone');
            $table->text('notes')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn(['code', 'username', 'contact_name', 'phone', 'email', 'notes']);
        });
    }
};
