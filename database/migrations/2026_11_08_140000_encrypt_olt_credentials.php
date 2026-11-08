<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * OLT login username and SNMP community stored encrypted with the app
     * key, like the password already is (the model casts them).
     */
    public function up(): void
    {
        Schema::table('olts', function (Blueprint $table) {
            $table->text('username')->change();
            $table->text('snmp')->nullable()->change();
        });

        foreach (DB::table('olts')->get(['id', 'username', 'snmp']) as $row) {
            $set = [];
            // An empty username is encrypted too (the cast can't read a bare ""); an empty community is null.
            if ($row->username === null || ! $this->isEncrypted($row->username)) {
                $set['username'] = Crypt::encryptString((string) $row->username);
            }
            if ($row->snmp === '') {
                $set['snmp'] = null;
            } elseif ($row->snmp !== null && ! $this->isEncrypted($row->snmp)) {
                $set['snmp'] = Crypt::encryptString($row->snmp);
            }
            if ($set) {
                DB::table('olts')->where('id', $row->id)->update($set);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('olts')->get(['id', 'username', 'snmp']) as $row) {
            $set = [];
            foreach (['username', 'snmp'] as $col) {
                if (filled($row->$col) && $this->isEncrypted($row->$col)) {
                    $set[$col] = Crypt::decryptString($row->$col);
                }
            }
            if ($set) {
                DB::table('olts')->where('id', $row->id)->update($set);
            }
        }

        Schema::table('olts', function (Blueprint $table) {
            $table->string('username')->change();
            $table->string('snmp')->nullable()->change();
        });
    }

    protected function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
};
