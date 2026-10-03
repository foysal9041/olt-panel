<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Month-end accounts:
     *
     * - Zone settlements belong to a billing cycle (Fixed date 11–10,
     *   Balunda 16–15, NTTN 1–30). Their Net Bill goes straight into the
     *   month's profit sheet, so the "post to income" columns go.
     * - Every Cash Book head says where it counts on the profit sheet
     *   (others income / fixed cost / others cost / not counted).
     * - Profit sheets: one per month, draft until finalized; finalizing
     *   locks the month and credits each partner.
     * - Partners (shareholders and commission holders) each have their own
     *   account: what they earned per month and what was paid to them.
     */
    public function up(): void
    {
        Schema::table('zone_settlements', function (Blueprint $table) {
            $table->string('cycle', 20)->default('fixed')->after('month');
            $table->dropConstrainedForeignId('posted_by');
            $table->dropColumn(['posted_at', 'posted_on']);
        });

        Schema::table('zone_settlement_rows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transaction_id');
        });

        Schema::table('transaction_categories', function (Blueprint $table) {
            $table->string('pl_group', 10)->nullable();
        });

        DB::table('transaction_categories')->where('type', 'expense')
            ->whereIn('name', ['বেতন', 'ইন্টারনেট বিল', 'মোবাইল রিচার্জ', 'তেল'])
            ->update(['pl_group' => 'fixed']);

        // Only ever used by settlement posting; remove it if it stayed empty.
        $old = DB::table('transaction_categories')->where('name', 'Zone Settlement')->where('type', 'income')->value('id');
        if ($old && ! DB::table('transactions')->where('transaction_category_id', $old)->exists()) {
            DB::table('transaction_categories')->where('id', $old)->delete();
        }

        Schema::create('profit_sheets', function (Blueprint $table) {
            $table->id();
            $table->date('month')->unique();
            $table->string('status', 10)->default('draft');
            $table->json('lines');
            $table->json('commission')->nullable();
            $table->json('shares')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('phone', 30)->nullable();
            $table->decimal('share', 8, 3)->default(0);
            $table->decimal('commission_percent', 6, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('partner_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->foreignId('profit_sheet_id')->nullable()->constrained()->nullOnDelete();
            $table->date('entry_date');
            $table->string('type', 20);
            $table->decimal('amount', 14, 2);
            $table->string('method', 30)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['partner_id', 'entry_date']);
        });

        $now = now();
        $partners = [
            ['Habibur Rahman', 26.5, 0], ['Nazmul Hasan Tusher', 19.5, 0], ['Md Noman Hossain', 16.5, 0],
            ['Md Jabber Mollik', 13, 0], ['Md Namul Hasan (Pappu)', 11.5, 0], ['Abu Hanif', 5, 0],
            ['Md Kamruzzman', 4, 0], ['MD Sawkot Akbor (Sujon)', 4, 0],
            ['Shawon', 0, 12.5], ['Tusher', 0, 7.5],
        ];
        foreach ($partners as $i => [$name, $share, $commission]) {
            DB::table('partners')->insert([
                'name' => $name, 'share' => $share, 'commission_percent' => $commission,
                'is_active' => true, 'sort' => $i + 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_entries');
        Schema::dropIfExists('partners');
        Schema::dropIfExists('profit_sheets');

        Schema::table('transaction_categories', function (Blueprint $table) {
            $table->dropColumn('pl_group');
        });

        Schema::table('zone_settlement_rows', function (Blueprint $table) {
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
        });

        Schema::table('zone_settlements', function (Blueprint $table) {
            $table->dropColumn('cycle');
            $table->timestamp('posted_at')->nullable();
            $table->date('posted_on')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }
};
