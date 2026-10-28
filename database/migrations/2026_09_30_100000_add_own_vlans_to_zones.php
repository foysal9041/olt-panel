<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A POP with its own switch has its own VLAN space (e.g. 101-108 at
     * several POPs), so its VLANs are only checked against that POP.
     */
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->boolean('own_vlans')->default(false)->after('notes');
        });

        // Existing POPs (names with "POP") get it switched on; others are set per zone.
        DB::table('zones')->where('name', 'like', '%POP%')->update(['own_vlans' => true]);
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn('own_vlans');
        });
    }
};
