<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
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

    protected $fillable = [
        'name',
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
        ];
    }

    protected function activityLogExcept(): array
    {
        return [
            'community', 'created_at', 'updated_at', 'status', 'fail_count',
            'sys_name', 'sys_descr', 'uptime_seconds', 'last_polled_at', 'last_error',
        ];
    }

    public function ports()
    {
        return $this->hasMany(SwitchPort::class)->orderBy('if_index');
    }

    public function events()
    {
        return $this->hasMany(SwitchEvent::class);
    }

    public function getVendorLabelAttribute(): string
    {
        return self::VENDORS[$this->vendor] ?? $this->vendor;
    }

    public function getUptimeHumanAttribute(): ?string
    {
        if ($this->uptime_seconds === null) {
            return null;
        }

        $s = $this->uptime_seconds;

        return sprintf('%dd %02dh %02dm', intdiv($s, 86400), intdiv($s % 86400, 3600), intdiv($s % 3600, 60));
    }
}
