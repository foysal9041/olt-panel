<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - Inventory & assets: products (items), their categories and every
     *   stock movement (opening, purchase, use, return, sale, damage, count).
     *   Stock, where things are used and what the company owns are all
     *   worked out from the movements.
     * - Tickets: customer / network problems, assigned to someone.
     * - Tasks: personal to-dos and work assigned to someone.
     * - work_updates: the timeline (comments, status changes) of a ticket
     *   or task.
     * - users.employee_id: which HR employee a login belongs to, for
     *   applying for leave from the dashboard.
     */
    public function up(): void
    {
        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('unit', 20)->default('pcs');
            // asset: still the company's where it's used (OLT, switch, cable laid)
            // consumable: used up (connectors, tie, tape)
            $table->string('kind', 20)->default('asset');
            $table->decimal('min_stock', 12, 2)->default(0);
            $table->decimal('sale_price', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['name', 'brand', 'model']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->decimal('quantity', 12, 2);
            // Cost of one unit: the price paid for opening/purchase, the
            // average cost at the time for everything else.
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->decimal('unit_price', 14, 2)->nullable(); // sale price
            $table->date('date');
            $table->string('party')->nullable();     // supplier / buyer
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location')->nullable();  // where it's used / returned from
            $table->string('reference')->nullable(); // memo / invoice no
            $table->text('serials')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['inventory_item_id', 'type']);
            $table->index('location');
            $table->index('date');
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('description')->nullable();
            $table->string('category', 30)->default('other');
            $table->string('priority', 10)->default('normal');
            $table->string('status', 20)->default('open');
            $table->string('source', 20)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('zone')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority']);
            $table->index('assigned_to');
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 10)->default('normal');
            $table->string('status', 20)->default('todo');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->index(['assigned_to', 'status']);
            $table->index(['created_by', 'status']);
        });

        Schema::create('work_updates', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body')->nullable();
            $table->json('changes')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->unique()->after('zone')->constrained()->nullOnDelete();
        });

        $now = now();
        foreach (['Router', 'ONU / ONT', 'OLT', 'Switch', 'SFP Module', 'Fiber Cable', 'Patch Cord', 'Splitter', 'Media Converter',
            'Power / UPS / Battery', 'Tools', 'Computer & Office', 'Furniture', 'Other'] as $name) {
            DB::table('inventory_categories')->insert(['name' => $name, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Link each login to the employee with the same name (or the only
        // employee whose name starts with it, e.g. "Nazmul" → "Nazmul Hasan").
        $employees = DB::table('employees')->get(['id', 'name']);
        foreach (DB::table('users')->get(['id', 'name']) as $user) {
            $name = mb_strtolower(trim($user->name));
            $match = $employees->filter(fn ($e) => mb_strtolower(trim($e->name)) === $name);
            if ($match->isEmpty()) {
                $match = $employees->filter(fn ($e) => str_starts_with(mb_strtolower(trim($e->name)) . ' ', $name . ' '));
            }
            if ($match->count() === 1 && ! DB::table('users')->where('employee_id', $match->first()->id)->exists()) {
                DB::table('users')->where('id', $user->id)->update(['employee_id' => $match->first()->id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
        });
        Schema::dropIfExists('work_updates');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_categories');
    }
};
