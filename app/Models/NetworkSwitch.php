<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class NetworkSwitch extends Model
{
    use LogsActivity;

    public const VENDORS = [
        'cisco'    => 'Cisco',
        'arista'   => 'Arista',
        'juniper'  => 'Juniper',
        'huawei'   => 'Huawei',
        'mikrotik' => 'MikroTik',
        'bdcom'    => 'BDCOM',
        'dcn'      => 'DCN',
        'generic'  => 'Other / Generic',
    ];

    /** Switches and routers are polled the same way (SNMP); routers also list VLAN, bridge and tunnel interfaces. */
    public const TYPES = [
        'switch' => 'Switch',
        'router' => 'Router',
    ];

    protected $fillable = [
        'name',
        'device_type',
        'ip',
        'zone',
        'vendor',
        'snmp_version',
        'community',
        'snmp_port',
        'is_active',
        'notify',
        'dom_rx_oid',
        'dom_tx_oid',
        'dom_temp_oid',
        'dom_divisor',
        'dom_power_unit',
    ];

    protected $hidden = ['community'];

    protected function casts(): array
    {
        return [
            'community' => 'encrypted',
            'is_active' => 'boolean',
            'notify' => 'boolean',
            'status' => 'integer',
            'last_polled_at' => 'datetime',
            'cpu_percent' => 'integer',
            'cpu_at' => 'datetime',
            'mem_percent' => 'integer',
            'mem_total_bytes' => 'integer',
            'snmp_ms' => 'integer',
            'sensors_at' => 'datetime',
        ];
    }

    protected function activityLogExcept(): array
    {
        return [
            'community', 'created_at', 'updated_at', 'status', 'fail_count',
            'sys_name', 'sys_descr', 'uptime_seconds', 'last_polled_at', 'last_error',
            'cpu_percent', 'cpu_source', 'cpu_at', 'mem_percent', 'mem_source', 'mem_total_bytes', 'snmp_ms', 'sensors_at',
        ];
    }

    public function ports()
    {
        return $this->hasMany(SwitchPort::class)->orderBy('if_index');
    }

    /** The device graphs worth showing, LibreNMS order (only what it reports). */
    public function graphTypes(): array
    {
        $classes = $this->sensors()->reorder()->distinct()->pluck('class')->all();
        $types = ['device_bits'];
        if ($this->cpu_source && $this->cpu_source !== 'none') {
            $types[] = 'device_cpu';
        }
        if ($this->mem_source && $this->mem_source !== 'none') {
            $types[] = 'device_mem';
        }
        foreach (array_keys(DeviceSensor::CLASSES) as $class) {
            if (in_array($class, $classes, true)) {
                $types[] = 'device_' . $class;
            }
        }

        return [...$types, 'device_uptime', 'device_snmp'];
    }

    public function sensors()
    {
        return $this->hasMany(DeviceSensor::class)->orderBy('class')->orderBy('name');
    }

    public function events()
    {
        return $this->hasMany(SwitchEvent::class);
    }

    public function getVendorLabelAttribute(): string
    {
        return self::VENDORS[$this->vendor] ?? $this->vendor;
    }

    /** The CPU % is from a recent poll. */
    public function hasCurrentCpu(): bool
    {
        return $this->cpu_percent !== null && $this->cpu_at && $this->cpu_at->gt(now()->subMinutes(5));
    }

    public function isRouter(): bool
    {
        return $this->device_type === 'router';
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->device_type] ?? self::TYPES['switch'];
    }

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        return array_key_exists((string) $type, self::TYPES) ? $query->where('device_type', $type) : $query;
    }

    public function getUptimeHumanAttribute(): ?string
    {
        if ($this->uptime_seconds === null) {
            return null;
        }

        $s = $this->uptime_seconds;

        return sprintf('%dd %02dh %02dm', intdiv($s, 86400), intdiv($s % 86400, 3600), intdiv($s % 3600, 60));
    }

    /**
     * Only the devices this user may see (see User::deviceAccess()).
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return match ($user->deviceAccess('switch')) {
            'all' => $query,
            'selected' => $query->whereIn($this->getTable() . '.id', $user->allowedSwitches()->select('network_switches.id')),
            default => $user->zone ? $query->where($this->getTable() . '.zone', $user->zone) : $query->whereRaw('1 = 0'),
        };
    }
}
