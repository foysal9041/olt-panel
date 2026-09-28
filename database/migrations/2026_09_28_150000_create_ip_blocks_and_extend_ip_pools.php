<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A parent range (e.g. the /24 bought from the upstream) that
        // individual subnets are carved out of.
        Schema::create('ip_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('cidr', 50)->unique();
            $table->string('type', 10)->default('public');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::table('ip_pools', function (Blueprint $table) {
            $table->foreignId('ip_block_id')->nullable()->after('id')->constrained('ip_blocks')->restrictOnDelete();
            $table->string('device')->nullable()->after('subnet');
            $table->string('purpose')->nullable()->after('device');
            $table->string('private_subnet', 50)->nullable()->after('purpose');
            // Network address as an integer, for sorting subnets in order.
            $table->unsignedInteger('network')->nullable()->after('subnet')->index();
        });

        // Pairing VLANs can be long lists like "2211-2214,2435-2439,...".
        Schema::table('ip_pools', function (Blueprint $table) {
            $table->string('vlan', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ip_pools', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ip_block_id');
            $table->dropColumn(['device', 'purpose', 'private_subnet', 'network']);
        });

        Schema::dropIfExists('ip_blocks');
    }
};
