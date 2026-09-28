<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE invoices MODIFY status ENUM('unpaid', 'partially_paid', 'paid') NOT NULL DEFAULT 'unpaid'");
    }

    public function down(): void
    {
        DB::statement("UPDATE invoices SET status = 'unpaid' WHERE status = 'partially_paid'");
        DB::statement("ALTER TABLE invoices MODIFY status ENUM('unpaid', 'paid') NOT NULL DEFAULT 'unpaid'");
    }
};
