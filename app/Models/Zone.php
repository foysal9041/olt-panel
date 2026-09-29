<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Zone extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'code',
        'username',
        'contact_name',
        'phone',
        'email',
        'notes',
        'own_vlans',
    ];

    protected $casts = [
        'own_vlans' => 'boolean',
    ];

    /**
     * Tables that record a zone by its name (the `zone` column). Renaming
     * a zone renames it in all of them, so the name is the same everywhere.
     */
    public const REFERENCED_BY = [
        'olts' => 'OLTs',
        'network_switches' => 'switches',
        'ip_pools' => 'IP subnets',
        'nttn_links' => 'NTTN links',
        'vlans' => 'VLANs',
        'users' => 'users',
        'employees' => 'employees',
        'customers' => 'customers',
        'zk_devices' => 'attendance devices',
        'transactions' => 'transactions',
    ];

    protected static function booted(): void
    {
        static::updating(function (Zone $zone) {
            $old = $zone->getOriginal('name');

            if ($zone->isDirty('name') && $old !== null) {
                foreach (array_keys(self::REFERENCED_BY) as $table) {
                    DB::table($table)->where('zone', $old)->update(['zone' => $zone->name]);
                }
            }
        });
    }

    /**
     * All zone names, for every zone dropdown in the panel.
     *
     * @return Collection<int, string>
     */
    public static function names(): Collection
    {
        return static::orderBy('name')->pluck('name');
    }

    /**
     * How many records use this zone, per table (only tables that do).
     *
     * @return array<string, int>
     */
    public function references(): array
    {
        $counts = [];

        foreach (self::REFERENCED_BY as $table => $label) {
            if ($n = DB::table($table)->where('zone', $this->name)->count()) {
                $counts[$label] = $n;
            }
        }

        return $counts;
    }

    /**
     * Phone numbers as a list (the column may hold "01711..., 01920...").
     *
     * @return list<string>
     */
    public function phones(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->phone))));
    }

    /**
     * olts.zone is a plain string matching zones.name (not a foreign key to
     * zones.id) — same convention used by users/employees/customers, kept
     * that way here to avoid a wider foreign-key migration across all of
     * them just for this.
     */
    public function olts()
    {
        return $this->hasMany(Olt::class, 'zone', 'name');
    }
}
