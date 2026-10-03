<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bandwidth client billing:
     *
     * - Service history: every rate revision or upgrade/downgrade is a dated
     *   row, so a month with a change mid-way is billed in parts.
     * - Every client is billed flat monthly: a full month is rate × Mbps;
     *   only a month with a change in it is split by days.
     * - A monthly invoice per client (made on the 1st), its lines, and the
     *   payments against it (several per month; part can stay due and is
     *   carried into the next invoice as "previous month due").
     * - Types such as VAS and the Billing charge are a fixed monthly amount,
     *   not billed by days.
     */
    public function up(): void
    {
        Schema::table('bandwidth_types', function (Blueprint $table) {
            $table->boolean('flat')->default(false);
            $table->unsignedSmallInteger('sort')->default(50);
        });

        foreach (['IIG' => 1, 'GGC' => 2, 'FNA' => 3, 'BDIX' => 4, 'CDN' => 5, 'NTTN' => 6, 'VAS' => 7] as $name => $sort) {
            DB::table('bandwidth_types')->where('name', $name)->update(['sort' => $sort, 'flat' => $name === 'VAS']);
        }
        if (! DB::table('bandwidth_types')->where('name', 'Billing')->exists()) {
            DB::table('bandwidth_types')->insert(['name' => 'Billing', 'flat' => true, 'sort' => 8, 'created_at' => now(), 'updated_at' => now()]);
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->string('contact_person', 100)->nullable()->after('name');
            $table->decimal('opening_due', 14, 2)->default(0);
        });

        Schema::create('bandwidth_service_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bandwidth_type_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->decimal('rate', 12, 4)->default(0);
            $table->decimal('mbps', 12, 2)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'bandwidth_type_id', 'effective_from'], 'bw_changes_lookup');
        });

        Schema::create('bandwidth_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('month');
            $table->string('invoice_no', 40)->unique();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('prepared_by', 100)->nullable();
            $table->string('prepared_title', 100)->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['customer_id', 'month']);
        });

        Schema::create('bandwidth_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bandwidth_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bandwidth_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label', 100);
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->decimal('rate', 12, 4)->nullable();
            $table->decimal('mbps', 12, 2)->nullable();
            $table->decimal('amount', 16, 4);
            $table->string('remark', 255)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('bandwidth_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('bandwidth_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->date('paid_on');
            $table->decimal('amount', 14, 2);
            $table->string('method', 20);
            $table->string('note', 255)->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'paid_on']);
        });

        // Today's rates become the first history rows, from the month they were set.
        foreach (DB::table('customer_bandwidth_rates')->get() as $r) {
            DB::table('bandwidth_service_changes')->insert([
                'customer_id' => $r->customer_id,
                'bandwidth_type_id' => $r->bandwidth_type_id,
                'effective_from' => substr((string) $r->created_at, 0, 7) . '-01',
                'rate' => $r->rate,
                'mbps' => $r->quantity,
                'note' => 'Rates when billing history started',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bandwidth_payments');
        Schema::dropIfExists('bandwidth_invoice_lines');
        Schema::dropIfExists('bandwidth_invoices');
        Schema::dropIfExists('bandwidth_service_changes');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['contact_person', 'opening_due']);
        });

        DB::table('bandwidth_types')->where('name', 'Billing')->delete();
        Schema::table('bandwidth_types', function (Blueprint $table) {
            $table->dropColumn(['flat', 'sort']);
        });
    }
};
