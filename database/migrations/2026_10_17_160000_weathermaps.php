<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weathermaps (as in Cacti / LibreNMS): nodes (switches, routers or free
 * labels like "IIG" or "Cloud") placed on a canvas, and links between
 * them, each tied to an interface whose live traffic colours the link.
 * Shared like graph dashboards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weathermaps', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visibility', 10)->default('private');
            $table->unsignedSmallInteger('width')->default(1200);
            $table->unsignedSmallInteger('height')->default(700);
            $table->string('label_mode', 10)->default('bps');
            $table->unsignedSmallInteger('refresh_seconds')->default(60);
            $table->timestamps();
        });

        Schema::create('weathermap_roles', function (Blueprint $table) {
            $table->foreignId('weathermap_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30);
            $table->boolean('can_edit')->default(false);
            $table->primary(['weathermap_id', 'role']);
        });

        Schema::create('weathermap_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weathermap_id')->constrained()->cascadeOnDelete();
            $table->foreignId('network_switch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label', 60);
            $table->string('icon', 20)->default('switch');
            $table->smallInteger('x')->default(100);
            $table->smallInteger('y')->default(100);
            $table->timestamps();
        });

        Schema::create('weathermap_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weathermap_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_node_id')->constrained('weathermap_nodes')->cascadeOnDelete();
            $table->foreignId('to_node_id')->constrained('weathermap_nodes')->cascadeOnDelete();
            $table->foreignId('switch_port_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('reverse')->default(false)->comment('true: the port\'s inbound is from → to');
            $table->unsignedInteger('bandwidth_mbps')->nullable()->comment('overrides the port speed for %');
            $table->string('label', 60)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weathermap_links');
        Schema::dropIfExists('weathermap_nodes');
        Schema::dropIfExists('weathermap_roles');
        Schema::dropIfExists('weathermaps');
    }
};
