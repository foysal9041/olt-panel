<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A line between two weathermap nodes, drawn as two arrows meeting in the
 * middle — from → to and to → from — each coloured by how full that
 * direction of its interface is. The interface's outbound is from → to,
 * unless reverse (then its inbound is).
 */
class WeathermapLink extends Model
{
    /**
     * The classic weathermap scale: up to this %, this colour.
     *
     * @var array<int, string>
     */
    public const SCALE = [1 => '#c0c0c0', 10 => '#8c00ff', 25 => '#2020ff', 40 => '#00c0ff', 55 => '#00f000', 70 => '#f0f000', 85 => '#ffc000', 100 => '#ff0000'];

    protected $fillable = ['weathermap_id', 'from_node_id', 'to_node_id', 'switch_port_id', 'reverse', 'bandwidth_mbps', 'label'];

    protected $casts = ['reverse' => 'boolean', 'bandwidth_mbps' => 'integer'];

    public function from()
    {
        return $this->belongsTo(WeathermapNode::class, 'from_node_id');
    }

    public function to()
    {
        return $this->belongsTo(WeathermapNode::class, 'to_node_id');
    }

    public function port()
    {
        return $this->belongsTo(SwitchPort::class, 'switch_port_id');
    }

    /** Capacity the % is worked out against: the override, else the port speed. */
    public function capacityBps(): ?int
    {
        $mbps = $this->bandwidth_mbps ?: $this->port?->speed_mbps;

        return $mbps ? $mbps * 1_000_000 : null;
    }

    /**
     * Live figures for the map: bps and % each way (null when unknown).
     *
     * @return array{ab: ?int, ba: ?int, abPct: ?float, baPct: ?float, capacity: ?int, port: ?string, fresh: bool}
     */
    public function live(): array
    {
        $port = $this->port;
        $fresh = $port && $port->hasCurrentTraffic();
        $ab = $fresh ? ($this->reverse ? $port->in_bps : $port->out_bps) : null;
        $ba = $fresh ? ($this->reverse ? $port->out_bps : $port->in_bps) : null;
        $cap = $this->capacityBps();

        return [
            'ab' => $ab,
            'ba' => $ba,
            'abPct' => $ab !== null && $cap ? round($ab / $cap * 100, 1) : null,
            'baPct' => $ba !== null && $cap ? round($ba / $cap * 100, 1) : null,
            'capacity' => $cap,
            'port' => $port ? ($port->networkSwitch?->name . ' · ' . $port->label) : null,
            'fresh' => $fresh,
        ];
    }

    public static function colour(?float $pct): string
    {
        if ($pct === null) {
            return '#e5e7eb';
        }
        foreach (self::SCALE as $upTo => $colour) {
            if ($pct <= $upTo) {
                return $colour;
            }
        }

        return '#ff0000';
    }
}
