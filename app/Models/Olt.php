<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Olt extends Model
{
    use LogsActivity;

    protected $fillable = [
        'zone',
        'brand',
        'model',
        'vlan',
        'name',
        'ip',
        'username',
        'password',
        'snmp',
        'status'
    ];

    /**
     * Encrypted at rest using the app key — stored ciphertext, not plaintext,
     * so a database leak alone doesn't expose OLT admin credentials.
     * Transparent to the rest of the app: reading/writing $olt->password
     * still works with the plain value, Eloquent handles the encrypt/decrypt.
     */
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
        ];
    }

    public function onus()
    {
        return $this->hasMany(
            Onu::class,
            'olt_id'
        );
    }
}

