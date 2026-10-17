<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Employee serial number: the order employees are listed in (employee
 * list, attendance, salary sheet, ID cards …), set by hand. Existing
 * employees start numbered in the order they were added.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedSmallInteger('serial_no')->nullable()->after('id')->index();
        });

        foreach (DB::table('employees')->orderBy('id')->pluck('id') as $i => $id) {
            DB::table('employees')->where('id', $id)->update(['serial_no' => $i + 1]);
        }
    }

    public function down(): void
    {
        Schema::table('employees', fn (Blueprint $table) => $table->dropColumn('serial_no'));
    }
};
