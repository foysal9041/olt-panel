<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nttn_links', function (Blueprint $table) {
            $table->string('public_ip_subnet')->nullable()->after('bandwidth');
            $table->string('private_ip_subnet')->nullable()->after('public_ip_subnet');
            $table->string('peering_ip')->nullable()->unique()->after('private_ip_subnet');
            $table->string('peering_vlan')->nullable()->after('peering_ip');
            $table->string('asn')->nullable()->after('peering_vlan');
        });
    }

    public function down(): void
    {
        Schema::table('nttn_links', function (Blueprint $table) {
            $table->dropColumn([
                'public_ip_subnet',
                'private_ip_subnet',
                'peering_ip',
                'peering_vlan',
                'asn',
            ]);
        });
    }
};
