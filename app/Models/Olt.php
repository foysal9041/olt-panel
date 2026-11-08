<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
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
        'status',
        'uplink_port_id',
    ];

    /** The switch / router port this OLT is connected to: its going down raises an "OLT uplink" alert. */
    public function uplinkPort()
    {
        return $this->belongsTo(SwitchPort::class, 'uplink_port_id');
    }

    /**
     * Encrypted at rest using the app key — stored ciphertext, not plaintext,
     * so a database leak alone doesn't expose OLT admin credentials.
     * Transparent to the rest of the app: reading/writing $olt->password
     * still works with the plain value, Eloquent handles the encrypt/decrypt.
     */
    protected function casts(): array
    {
        return [
            'username' => 'encrypted',
            'password' => 'encrypted',
            'snmp' => 'encrypted',
        ];
    }

    /** Never in arrays / JSON: shown only through OltController::reveal. */
    protected $hidden = ['username', 'password', 'snmp'];

    /** Credentials in the activity log only as "changed", never their values. */
    protected function loggableChanges(): array
    {
        $changes = [];
        foreach ($this->getChanges() as $key => $new) {
            if (in_array($key, ['created_at', 'updated_at'], true)) {
                continue;
            }
            $changes[$key] = in_array($key, ['username', 'password', 'snmp'], true)
                ? ['old' => '•••', 'new' => '(changed)']
                : ['old' => $this->getOriginal($key), 'new' => $new];
        }

        return $changes;
    }

    public function onus()
    {
        return $this->hasMany(
            Onu::class,
            'olt_id'
        );
    }

    /**
     * Only the devices this user may see (see User::deviceAccess()).
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return match ($user->deviceAccess('olt')) {
            'all' => $query,
            'selected' => $query->whereIn($this->getTable() . '.id', $user->allowedOlts()->select('olts.id')),
            default => $user->zone ? $query->where($this->getTable() . '.zone', $user->zone) : $query->whereRaw('1 = 0'),
        };
    }
}
