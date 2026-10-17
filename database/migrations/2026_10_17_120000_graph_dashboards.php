<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Graph dashboards (as in LibreNMS): a user picks interfaces from any
 * switches they can see and lays their traffic graphs out on a dashboard,
 * shared with chosen roles (view, or view and edit) or with everyone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graph_dashboards', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visibility', 10)->default('private');
            $table->string('default_graph', 10)->default('day');
            $table->unsignedSmallInteger('refresh_seconds')->default(60);
            $table->timestamps();
        });

        Schema::create('graph_dashboard_roles', function (Blueprint $table) {
            $table->foreignId('graph_dashboard_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30);
            $table->boolean('can_edit')->default(false);
            $table->primary(['graph_dashboard_id', 'role']);
        });

        Schema::create('graph_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('graph_dashboard_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->default('traffic');
            $table->string('title', 150)->nullable();
            $table->unsignedTinyInteger('width')->default(6);
            $table->string('graph', 10)->nullable();
            $table->json('settings')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->index(['graph_dashboard_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graph_widgets');
        Schema::dropIfExists('graph_dashboard_roles');
        Schema::dropIfExists('graph_dashboards');
    }
};
