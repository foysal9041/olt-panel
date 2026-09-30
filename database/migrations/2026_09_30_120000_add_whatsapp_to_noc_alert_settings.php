<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * WhatsApp (Meta WhatsApp Business Cloud API) as a second alert channel.
     */
    public function up(): void
    {
        Schema::table('noc_alert_settings', function (Blueprint $table) {
            $table->boolean('whatsapp_enabled')->default(false);
            $table->string('whatsapp_phone_number_id', 40)->nullable();
            $table->text('whatsapp_token')->nullable();               // encrypted
            $table->string('whatsapp_recipients', 500)->nullable();   // 8801XXXXXXXXX, …
            $table->string('whatsapp_mode', 10)->default('template'); // template | text
            $table->string('whatsapp_template', 100)->nullable()->default('noc_alert');
            $table->string('whatsapp_template_lang', 12)->default('en');
            $table->string('whatsapp_api_version', 10)->default('v23.0');
        });
    }

    public function down(): void
    {
        Schema::table('noc_alert_settings', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_enabled', 'whatsapp_phone_number_id', 'whatsapp_token', 'whatsapp_recipients',
                'whatsapp_mode', 'whatsapp_template', 'whatsapp_template_lang', 'whatsapp_api_version',
            ]);
        });
    }
};
