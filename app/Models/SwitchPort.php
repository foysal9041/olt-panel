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
        'in_octets' => 'integer',
        'out_octets' => 'integer',
        'octets_at' => 'integer',
        'counter_bits' => 'integer',
        'in_bps' => 'integer',
        'out_bps' => 'integer',
        'traffic_at' => 'datetime',
        'util_alarm' => 'boolean',
        'pkt_counters' => 'array',
        'pkt_rates' => 'array',
        'util_streak' => 'integer',
    ];

    public function networkSwitch()
    {
        return $this->belongsTo(NetworkSwitch::class);
    }

    public function readings()
    {
        return $this->hasMany(SwitchPortReading::class);
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
     * The Rx level below which this port is in warning (and alerts), and
     * where it came from. Priority: the per-speed rule from Settings
     * (10G+ / 1G), then the module's own low-warning limit, then the
     * global fallback.
     *
     * @return array{value: ?float, source: string, label: string}
     */
    public function rxWarning(NocAlertSetting $settings): array
    {
        $speed = (int) $this->speed_mbps;

        if ($speed >= 10000 && $settings->rx_warn_10g !== null) {
            return ['value' => $settings->rx_warn_10g, 'source' => '10g', 'label' => '10G rule'];
        }

        if ($speed >= 1000 && $speed < 10000 && $settings->rx_warn_1g !== null) {
            return ['value' => $settings->rx_warn_1g, 'source' => '1g', 'label' => '1G rule'];
        }

        if ($this->rx_low_warn !== null) {
            return ['value' => $this->rx_low_warn, 'source' => 'module', 'label' => 'module low warning'];
        }

        return ['value' => $settings->rx_low_threshold, 'source' => 'fallback', 'label' => 'fallback threshold'];
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

    public function traffic()
    {
        return $this->hasMany(SwitchPortTraffic::class);
    }

    /** The traffic rate is from a recent poll (not a port that stopped being polled). */
    public function hasCurrentTraffic(): bool
    {
        return $this->in_bps !== null && $this->traffic_at && $this->traffic_at->gt(now()->subMinutes(5));
    }

    /** Share of the link speed in use by the busier direction, 0–100, or null. */
    public function utilisation(): ?float
    {
        if (! $this->speed_mbps || ! $this->hasCurrentTraffic()) {
            return null;
        }

        return min(100, max($this->in_bps, $this->out_bps) / ($this->speed_mbps * 1_000_000) * 100);
    }

    /** 1234567 → "1.23 Mbps" (bits, 1000 steps — as the graphs show them). */
    public static function formatBps(?int $bps): string
    {
        if ($bps === null) {
            return '—';
        }
        $units = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
        $v = (float) $bps;
        $i = 0;
        while ($v >= 1000 && $i < count($units) - 1) {
            $v /= 1000;
            $i++;
        }

        return ($i === 0 ? (string) round($v) : number_format($v, $v >= 100 ? 0 : ($v >= 10 ? 1 : 2))) . ' ' . $units[$i];
    }
}
