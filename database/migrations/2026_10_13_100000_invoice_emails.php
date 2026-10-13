<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Emailing invoices and money receipts to clients: the client's address,
 * the office's outgoing mail (SMTP) settings, and a record of every mail
 * sent (or that failed) against the invoice / receipt it carried.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // One or more addresses, comma separated.
            $table->string('email', 500)->nullable()->after('phone');
        });

        Schema::create('mail_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('port')->default(465);
            $table->string('encryption', 10)->default('ssl');
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();
            $table->string('reply_to')->nullable();
            $table->string('bcc', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('sent_mails', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('document');
            $table->string('kind', 30);
            $table->string('to', 500);
            $table->string('subject');
            $table->string('status', 10)->default('sending');
            $table->text('error')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_mails');
        Schema::dropIfExists('mail_settings');
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('email'));
    }
};
