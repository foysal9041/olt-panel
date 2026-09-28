<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('user_module_permissions', 'submodule')) {
            Schema::table('user_module_permissions', function (Blueprint $table) {
                // '' means "whole module" (every submodule); a specific
                // value grants only that submodule. See User::hasModuleAccess().
                $table->string('submodule')->default('')->after('module');
            });
        }

        // The standalone 'alerts' module no longer exists — drop any
        // leftover grants for it.
        DB::table('user_module_permissions')->where('module', 'alerts')->delete();

        // 'users' folded into 'settings' as the 'users' submodule.
        DB::table('user_module_permissions')
            ->where('module', 'users')
            ->update(['module' => 'settings', 'submodule' => 'users']);

        // Old index only covered (user_id, module) and is relied on by the
        // user_id foreign key, so the replacement must be added before the
        // old one is dropped or MySQL refuses the drop.
        Schema::table('user_module_permissions', function (Blueprint $table) {
            $table->unique(['user_id', 'module', 'submodule']);
        });

        Schema::table('user_module_permissions', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'module']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_module_permissions', function (Blueprint $table) {
            $table->unique(['user_id', 'module']);
        });

        Schema::table('user_module_permissions', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'module', 'submodule']);
        });

        DB::table('user_module_permissions')
            ->where('module', 'settings')
            ->where('submodule', 'users')
            ->update(['module' => 'users', 'submodule' => '']);

        Schema::table('user_module_permissions', function (Blueprint $table) {
            $table->dropColumn('submodule');
        });
    }
};
