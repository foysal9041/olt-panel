<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * One box on a graph dashboard:
 *   traffic — the traffic graph of one interface, or of several added up
 *             (settings.ports = [switch_port ids]);
 *   top     — the busiest interfaces right now on chosen switches
 *             (settings.switches = [ids], empty = all; settings.limit);
 *   cpu     — a device graph of one switch or router: CPU, memory, total
 *             traffic, sensors, uptime … (settings.device, settings.metric).
 */
class GraphWidget extends Model
{
    use LogsActivity;

    public const KINDS = [
        'traffic' => 'Traffic graph',
        'top' => 'Top interfaces',
        'cpu' => 'Device graph',
    ];

    /** Device graph metrics (GraphEngine "device_…" types). */
    public const DEVICE_METRICS = ['cpu', 'mem', 'bits', 'temperature', 'fan', 'voltage', 'current', 'power', 'uptime', 'snmp'];

    public function metric(): string
    {
        $m = $this->settings['metric'] ?? 'cpu';

        return in_array($m, self::DEVICE_METRICS, true) ? $m : 'cpu';
    }

    /** Bootstrap columns: full, half, third of the row. */
    public const WIDTHS = [12 => 'Full width', 6 => 'Half', 4 => 'Third'];

    protected $fillable = ['graph_dashboard_id', 'kind', 'title', 'width', 'graph', 'settings', 'position'];

    protected $casts = [
        'settings' => 'array',
        'width' => 'integer',
        'position' => 'integer',
    ];

    public function dashboard()
    {
        return $this->belongsTo(GraphDashboard::class, 'graph_dashboard_id');
    }

    /** @return list<int> */
    public function portIds(): array
    {
        return array_values(array_map('intval', $this->settings['ports'] ?? []));
    }

    /** @return list<int> */
    public function switchIds(): array
    {
        return array_values(array_map('intval', $this->settings['switches'] ?? []));
    }

    public function deviceId(): ?int
    {
        return isset($this->settings['device']) ? (int) $this->settings['device'] : null;
    }

    public function device(): ?NetworkSwitch
    {
        return $this->deviceId() ? NetworkSwitch::find($this->deviceId()) : null;
    }

    /** Out drawn below the axis (LibreNMS "inverse" style). */
    public function mirror(): bool
    {
        return (bool) ($this->settings['mirror'] ?? false);
    }

    public function limit(): int
    {
        return max(3, min(25, (int) ($this->settings['limit'] ?? 10)));
    }

    /** The interfaces on a traffic graph that still exist, with their switch. */
    public function ports(): Collection
    {
        return SwitchPort::with('networkSwitch:id,name')->whereIn('id', $this->portIds())->get()
            ->sortBy(fn ($p) => array_search($p->id, $this->portIds(), true))->values();
    }

    /** Title shown on the box: its own, else the interface (or "N interfaces"). */
    public function displayTitle(?Collection $ports = null): string
    {
        if (filled($this->title)) {
            return $this->title;
        }
        if ($this->kind === 'top') {
            return 'Top interfaces';
        }
        if ($this->kind === 'cpu') {
            return \App\Services\GraphEngine::TYPES['device_' . $this->metric()][0] . ' · ' . ($this->relationLoaded('cpuDevice') ? $this->getRelation('cpuDevice')?->name : $this->device()?->name);
        }
        $ports ??= $this->ports();

        // One interface: its description (as LibreNMS shows it), else switch · port.
        return $ports->count() === 1
            ? ($ports->first()->alias ?: $ports->first()->networkSwitch?->name . ' · ' . $ports->first()->label)
            : $ports->count() . ' interfaces added up';
    }

    protected function activityLogLabel(): string
    {
        return 'Graph Widget';
    }

    protected function activityLogTitle(): string
    {
        return ($this->dashboard?->name ?? '') . ' · ' . ($this->title ?: self::KINDS[$this->kind] ?? $this->kind);
    }
}
