<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Default pay for the monthly salary sheet.
        Schema::table('employees', function (Blueprint $table) {
            $table->string('emp_code', 30)->nullable()->after('id');
            $table->decimal('basic_salary', 12, 2)->nullable()->after('designation');
            $table->decimal('house_rent', 12, 2)->nullable()->after('basic_salary');
        });

        // One salary sheet per month; each line becomes a বেতন expense when posted.
        Schema::create('salary_sheets', function (Blueprint $table) {
            $table->id();
            $table->date('month')->unique();          // first day of the month
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('salary_sheet_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_sheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('emp_code', 30)->nullable();
            $table->string('name');
            $table->string('designation')->nullable();
            $table->decimal('salary', 12, 2)->default(0);
            $table->decimal('house_rent', 12, 2)->default(0);
            $table->decimal('bonus', 12, 2)->default(0);
            $table->decimal('advance', 12, 2)->default(0);
            $table->decimal('deduction', 12, 2)->default(0);
            $table->decimal('net', 12, 2)->default(0);
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // The ledgers (খাত) used in the office's Excel books.
        $now = now();
        foreach ([
            ['জমা', 'income'],
            ['আপ্যায়ন', 'expense'],
            ['ইন্টারনেট মালামাল', 'expense'],
            ['মোবাইল রিচার্জ', 'expense'],
            ['তেল', 'expense'],
            ['অন্যান্য খরচ', 'expense'],
            ['বেতন', 'expense'],
            ['ইন্টারনেট বিল', 'expense'],
        ] as [$name, $type]) {
            if (! DB::table('transaction_categories')->where('name', $name)->where('type', $type)->exists()) {
                DB::table('transaction_categories')->insert(['name' => $name, 'type' => $type, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_sheet_items');
        Schema::dropIfExists('salary_sheets');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['emp_code', 'basic_salary', 'house_rent']);
        });
    }
};
