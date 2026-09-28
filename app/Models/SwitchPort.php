<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SwitchPort extends Model
{
    public const UP = 1;
    public const DOWN = 2;

    protected $fillable = [
        'network_switch_id',
        'if_index',
        'name',
        'descr',
        'alias',
        'if_type',
        'admin_status',
        'oper_status',
        'speed_mbps',
        'last_change_at',
        'notify',
        'rx_power',
        'rx_high_alarm',
        'rx_high_warn',
        'rx_low_warn',
        'rx_low_alarm',
        'tx_power',
        'temperature',
        'voltage',
        'bias',
        'rx_alarm',
        'dom_updated_at',
    ];

    protected $casts = [
        'notify' => 'boolean',
        'rx_alarm' => 'boolean',
        'last_change_at' => 'datetime',
        'dom_updated_at' => 'datetime',
    ];

    public function networkSwitch()
    {
        return $this->belongsTo(NetworkSwitch::class);
    }

    public function getLabelAttribute(): string
    {
        return $this->name ?: ($this->descr ?: "ifIndex {$this->if_index}");
    }

    public function getHasTransceiverAttribute(): bool
    {
        return $this->rx_power !== null || $this->tx_power !== null || $this->temperature !== null;
    }

    /**
     * Rx low limit that triggers an alert: the module's own low-warning
     * threshold when the switch reports one, else the global setting.
     */
    public function rxAlertThreshold(?float $fallback): ?float
    {
        return $this->rx_low_warn ?? $fallback;
    }

    public function getSpeedLabelAttribute(): ?string
    {
        if (! $this->speed_mbps) {
            return null;
        }

        return $this->speed_mbps >= 1000
            ? rtrim(rtrim(number_format($this->speed_mbps / 1000, 1), '0'), '.') . 'G'
            : $this->speed_mbps . 'M';
    }
}
