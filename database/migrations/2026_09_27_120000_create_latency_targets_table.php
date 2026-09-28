<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('latency_targets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('host');
            $table->string('group')->nullable()->index();
            $table->unsignedTinyInteger('pings')->default(20);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();

            // Denormalised copy of the latest probe so the dashboard list
            // doesn't have to query latency_probes for every card.
            $table->timestamp('last_probed_at')->nullable();
            $table->float('last_median')->nullable();
            $table->float('last_loss')->nullable();

            $table->timestamps();
        });

        Schema::create('latency_probes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('latency_target_id')->constrained()->cascadeOnDelete();
            $table->timestamp('probed_at');
            $table->unsignedTinyInteger('sent');
            $table->unsignedTinyInteger('received');
            $table->float('median')->nullable();
            $table->float('min')->nullable();
            $table->float('max')->nullable();
            // Every reply's RTT (ms), sorted ascending — the "smoke".
            $table->json('rtts')->nullable();

            $table->index(['latency_target_id', 'probed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('latency_probes');
        Schema::dropIfExists('latency_targets');
    }
};
