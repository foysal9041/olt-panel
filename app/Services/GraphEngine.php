<?php

namespace App\Services;

use App\Models\DeviceSensor;
use App\Models\NetworkSwitch;
use App\Models\SwitchPort;
use App\Models\SwitchPortTraffic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every graph of the panel, LibreNMS style: one window (a period such as
 * "24 Hours" or any From–To range, which picks the finest resolution that
 * still holds it) and one or more series, each with its legend — current,
 * average, maximum, 95th percentile and, where it makes sense, the total.
 *
 * Output: { type, title, unit, step, times[], series[{name, color, fill,
 * values[], peaks[]|null, legend{}}], p95, capacity, totalUnit, from, to }
 */
class GraphEngine
{
    /** Graph type => [title, subject: port | device | ports]. */
    public const TYPES = [
        'port_bits' => ['Traffic', 'port'],
        'port_pkts' => ['Unicast packets', 'port'],
        'port_nupkts' => ['Non-unicast packets', 'port'],
        'port_errors' => ['Errors', 'port'],
        'port_discards' => ['Discards', 'port'],
        'ports_bits' => ['Traffic', 'ports'],
        'device_bits' => ['Traffic (all ports)', 'device'],
        'device_cpu' => ['Processor', 'device'],
        'device_mem' => ['Memory', 'device'],
        'device_temperature' => ['Temperature', 'device'],
        'device_fan' => ['Fan speed', 'device'],
        'device_voltage' => ['Voltage', 'device'],
        'device_current' => ['Current', 'device'],
        'device_power' => ['Power', 'device'],
        'device_uptime' => ['Uptime', 'device'],
        'device_snmp' => ['SNMP response time', 'device'],
    ];

    /** Port graph types shown on a port page, in LibreNMS order. */
    public const PORT_GRAPHS = ['port_bits', 'port_pkts', 'port_nupkts', 'port_errors', 'port_discards'];

    protected const IN = '#16a34a';

    protected const OUT = '#1d4ed8';

    protected const PALETTE = ['#ea580c', '#2563eb', '#16a34a', '#9333ea', '#db2777', '#0891b2', '#ca8a04', '#64748b', '#dc2626', '#4f46e5'];

    /** A reading older than this (seconds) isn't "current". */
    protected const CURRENT_FOR = 300;

    /**
     * RRD's heartbeat: a missed poll leaves an empty minute (the next poll's
     * rate covers it), so gaps up to this many 1-minute buckets are filled
     * in from both sides instead of cutting a white line through the graph.
     */
    protected const HEARTBEAT = 5;

    /**
     * The time window: a period key, or from/to (custom range). Picks the
     * finest bucket size whose history reaches back to "from" and keeps the
     * graph under ~3000 points; avg = 5 merges 1-minute buckets into 5.
     *
     * @return array{key: ?string, title: string, from: int, to: int, res: int, step: int, first: int, last: int}
     */
    public function window(?string $key, ?int $from = null, ?int $to = null, int $avg = 1): array
    {
        $now = time();
        if ($from && $to && $from < $to) {
            $to = min($to, $now);
            $from = min($from, $to - 300);
            $res = 1440;
            foreach (SwitchPortTraffic::RESOLUTIONS as $r => $days) {
                if ($now - $from <= $days * 86400 && ($to - $from) / ($r * 60) <= 3000) {
                    $res = $r;
                    break;
                }
            }
            $title = 'Custom';
            $key = null;
        } else {
            [$title, $span, $res] = SwitchPortTraffic::GRAPHS[$key] ?? SwitchPortTraffic::GRAPHS['day'];
            $key = isset(SwitchPortTraffic::GRAPHS[$key]) ? $key : 'day';
            $to = $now;
            $from = $now - $span + $res * 60;
        }

        return [
            'key' => $key,
            'title' => $title,
            'from' => $from,
            'to' => $to,
            'res' => $res,
            'step' => $res === 1 && $avg === 5 ? 300 : $res * 60,
            'first' => SwitchPortTraffic::bucket($from, $res),
            'last' => SwitchPortTraffic::bucket($to, $res),
        ];
    }

    /**
     * @param  SwitchPort|NetworkSwitch|Collection  $subject  a port, a device, or several ports (ports_bits)
     * @param  int|null  $points  shrink to about this many points (small graphs)
     */
    public function build(string $type, $subject, array $w, ?int $points = null): array
    {
        $out = match ($type) {
            'port_bits' => $this->portBits($subject, $w),
            'port_pkts' => $this->portPackets($subject, $w, 'pkt', 'in_u', 'out_u', true),
            'port_nupkts' => $this->portPackets($subject, $w, 'npkt', 'in_n', 'out_n', false),
            'port_errors' => $this->portFaults($subject, $w, 'err', 'in_e', 'out_e'),
            'port_discards' => $this->portFaults($subject, $w, 'disc', 'in_d', 'out_d'),
            'ports_bits' => $this->portsBits($subject, $w),
            'device_bits' => $this->portsBits($subject->ports()->where('if_type', '!=', 161)->whereNotNull('counter_bits')->get(), $w),
            'device_cpu' => $this->deviceCpu($subject, $w),
            'device_mem' => $this->deviceMetric($subject, $w, 'mem', 'Memory used', 'percent', $subject->mem_percent, '#9333ea'),
            'device_uptime' => $this->deviceMetric($subject, $w, 'uptime', 'Uptime', 'days', $subject->uptime_seconds !== null ? $subject->uptime_seconds / 86400 : null, '#0891b2', true),
            'device_snmp' => $this->deviceMetric($subject, $w, 'snmp_ms', 'Response', 'ms', $subject->snmp_ms, '#ca8a04'),
            'device_temperature', 'device_fan', 'device_voltage', 'device_current', 'device_power' => $this->deviceSensors($subject, $w, substr($type, 7)),
            default => abort(404, 'Unknown graph type.'),
        };

        // Missed polls filled in; the current minute (not polled yet) left off the end.
        if ($w['res'] === 1) {
            foreach ($out['series'] as &$s) {
                $s['values'] = $this->fillGaps($s['values']);
                $s['peaks'] = $s['peaks'] !== null ? $this->fillGaps($s['peaks']) : null;
            }
            unset($s);
            $this->trimUnpolled($out);
        }

        // 5-minute averages, then the small-graph shrink.
        $times = $out['times'];
        $step = $w['step'];
        foreach ($out['series'] as &$s) {
            if ($w['step'] > $w['res'] * 60) {
                [$t, $s['values'], $s['peaks']] = $this->regroup($times, $s['values'], $s['peaks'], $w['step']);
            }
            $s['legend']['p95'] = $s['legend']['p95'] ?? $this->p95($s['values']);
            if (! empty($out['noP95'])) {
                $s['legend']['p95'] = null;
            }
        }
        unset($s);
        if ($w['step'] > $w['res'] * 60) {
            $times = $this->regroup($times, [], null, $w['step'])[0];
        }
        if ($points && count($times) > $points) {
            $n = (int) ceil(count($times) / $points);
            foreach ($out['series'] as &$s) {
                [, $s['values'], $s['peaks']] = $this->regroup($times, $s['values'], $s['peaks'], null, $n);
            }
            unset($s);
            $times = $this->regroup($times, [], null, null, $n)[0];
            $step *= $n;
        }

        $p95 = null;
        if (in_array($type, ['port_bits', 'ports_bits', 'device_bits', 'device_cpu'], true)) {
            $p95 = collect($out['series'])->max(fn ($s) => $s['legend']['p95']) ?: null;
        }

        return [
            'type' => $type,
            'title' => self::TYPES[$type][0],
            'period' => $w['title'],
            'key' => $w['key'],
            'unit' => $out['unit'],
            'step' => $step,
            'start' => $times[0] ?? $w['first'],
            'end' => $w['last'] + $w['res'] * 60,
            'from' => $w['from'],
            'to' => $w['to'],
            'times' => $times,
            'series' => $out['series'],
            'p95' => $p95,
            'capacity' => $out['capacity'] ?? null,
            'totalUnit' => $out['totalUnit'] ?? null,
            'empty' => $out['empty'] ?? null,
        ];
    }

    /** CSV of a built graph: time, then each series' value (and peak). */
    public function csv(array $g): string
    {
        $head = ['time'];
        foreach ($g['series'] as $s) {
            $head[] = $s['name'] . " ({$g['unit']})";
            if ($s['peaks']) {
                $head[] = $s['name'] . ' peak';
            }
        }
        $lines = [implode(',', array_map(fn ($h) => '"' . str_replace('"', '""', $h) . '"', $head))];
        foreach ($g['times'] as $i => $t) {
            $row = [date('Y-m-d H:i:s', $t)];
            foreach ($g['series'] as $s) {
                $row[] = $s['values'][$i] ?? '';
                if ($s['peaks']) {
                    $row[] = $s['peaks'][$i] ?? '';
                }
            }
            $lines[] = implode(',', $row);
        }

        return implode("\n", $lines) . "\n";
    }

    // ---- Port graphs --------------------------------------------------------

    protected function portRows(SwitchPort $port, array $w): Collection
    {
        return DB::table('switch_port_traffic')->where('switch_port_id', $port->id)->where('res', $w['res'])
            ->whereBetween('at', [$w['first'], $w['last']])->get()->keyBy('at');
    }

    protected function portBits(SwitchPort $port, array $w): array
    {
        $rows = $this->portRows($port, $w)->filter(fn ($r) => $r->samples > 0);
        $fresh = $port->hasCurrentTraffic();
        $series = [];
        foreach ([['In', 'in', self::IN, true], ['Out', 'out', self::OUT, false]] as [$name, $dir, $color, $fill]) {
            [$times, $values, $peaks] = $this->align($w, $rows, fn ($r) => intdiv((int) $r->{"{$dir}_sum"}, (int) $r->samples), fn ($r) => (int) $r->{"{$dir}_max"});
            $series[] = $this->series($name, $color, $fill, $values, $peaks, $fresh ? $port->{"{$dir}_bps"} : null, (int) round($rows->sum("{$dir}_sum") * 60 / 8));
        }

        return ['times' => $times, 'series' => $series, 'unit' => 'bps', 'capacity' => $port->speed_mbps ? $port->speed_mbps * 1_000_000 : null, 'totalUnit' => 'bytes'];
    }

    protected function portPackets(SwitchPort $port, array $w, string $col, string $curIn, string $curOut, bool $withPeaks): array
    {
        $rows = $this->portRows($port, $w)->filter(fn ($r) => $r->pkt_samples > 0);
        $rates = $port->hasCurrentTraffic() ? ($port->pkt_rates ?? []) : [];
        $series = [];
        foreach ([['In', 'in', self::IN, true, $curIn], ['Out', 'out', self::OUT, false, $curOut]] as [$name, $dir, $color, $fill, $cur]) {
            [$times, $values, $peaks] = $this->align($w, $rows, fn ($r) => intdiv((int) $r->{"{$col}_{$dir}_sum"}, (int) $r->pkt_samples), $withPeaks ? fn ($r) => (int) $r->{"{$col}_{$dir}_max"} : null);
            $series[] = $this->series($name, $color, $fill, $values, $peaks, $rates[$cur] ?? null, (int) round($rows->sum("{$col}_{$dir}_sum") * 60));
        }

        return ['times' => $times, 'series' => $series, 'unit' => 'pps', 'totalUnit' => 'count', 'empty' => 'Packet counts are read from the first poll after this was switched on.'];
    }

    /** Errors / discards: stored in thousandths per second. */
    protected function portFaults(SwitchPort $port, array $w, string $col, string $curIn, string $curOut): array
    {
        $rows = $this->portRows($port, $w)->filter(fn ($r) => $r->pkt_samples > 0);
        $rates = $port->hasCurrentTraffic() ? ($port->pkt_rates ?? []) : [];
        $series = [];
        foreach ([['In', 'in', '#dc2626', true, $curIn], ['Out', 'out', '#7c3aed', false, $curOut]] as [$name, $dir, $color, $fill, $cur]) {
            [$times, $values] = $this->align($w, $rows, fn ($r) => round($r->{"{$col}_{$dir}_sum"} / $r->pkt_samples / 1000, 3));
            $series[] = $this->series($name, $color, $fill, $values, null, isset($rates[$cur]) ? $rates[$cur] / 1000 : null, (int) round($rows->sum("{$col}_{$dir}_sum") * 60 / 1000));
        }

        return ['times' => $times, 'series' => $series, 'unit' => 'per_s', 'totalUnit' => 'count'];
    }

    /** Several ports added up (a dashboard's aggregate graph, or a device's traffic). */
    protected function portsBits(Collection $ports, array $w): array
    {
        $rows = $ports->isEmpty() ? collect() : DB::table('switch_port_traffic')
            ->whereIn('switch_port_id', $ports->pluck('id'))->where('res', $w['res'])->whereBetween('at', [$w['first'], $w['last']])->where('samples', '>', 0)
            ->groupBy('at')
            ->get(['at', DB::raw('SUM(in_sum DIV samples) AS i'), DB::raw('SUM(out_sum DIV samples) AS o'), DB::raw('SUM(in_sum) AS ti'), DB::raw('SUM(out_sum) AS t_o')])
            ->keyBy('at');
        $fresh = $ports->filter->hasCurrentTraffic();
        $series = [];
        foreach ([['In', 'i', 'ti', 'in_bps', self::IN, true], ['Out', 'o', 't_o', 'out_bps', self::OUT, false]] as [$name, $col, $total, $cur, $color, $fill]) {
            [$times, $values] = $this->align($w, $rows, fn ($r) => (int) $r->{$col});
            $series[] = $this->series($name, $color, $fill, $values, null, $fresh->isEmpty() ? null : (int) $fresh->sum($cur), (int) round($rows->sum($total) * 60 / 8));
        }

        return [
            'times' => $times, 'series' => $series, 'unit' => 'bps', 'totalUnit' => 'bytes',
            'capacity' => $ports->isNotEmpty() && $ports->every(fn ($p) => $p->speed_mbps) ? (int) $ports->sum(fn ($p) => $p->speed_mbps * 1_000_000) : null,
        ];
    }

    // ---- Device graphs ------------------------------------------------------

    protected function deviceCpu(NetworkSwitch $device, array $w): array
    {
        $rows = DB::table('device_cpu')->where('network_switch_id', $device->id)->where('res', $w['res'])
            ->whereBetween('at', [$w['first'], $w['last']])->where('samples', '>', 0)->get()->keyBy('at');
        [$times, $values, $peaks] = $this->align($w, $rows, fn ($r) => round($r->cpu_sum / $r->samples, 1), fn ($r) => (int) $r->cpu_max);

        return ['times' => $times, 'unit' => 'percent', 'series' => [$this->series('CPU', '#ea580c', true, $values, $peaks, $device->hasCurrentCpu() ? $device->cpu_percent : null)]];
    }

    protected function deviceMetric(NetworkSwitch $device, array $w, string $metric, string $name, string $unit, $current, string $color, bool $last = false): array
    {
        $scale = MetricStore::scale($metric);
        $div = $unit === 'days' ? 86400 : $scale;
        $rows = DB::table('device_metrics')->where('network_switch_id', $device->id)->where('metric', $metric)->where('res', $w['res'])
            ->whereBetween('at', [$w['first'], $w['last']])->where('samples', '>', 0)->get()->keyBy('at');
        // Uptime: the latest value in each bucket matters, which is its highest.
        [$times, $values, $peaks] = $this->align($w, $rows,
            $last ? fn ($r) => round($r->value_max / $div, 2) : fn ($r) => round($r->value_sum / $r->samples / $div, 2),
            $last ? null : fn ($r) => round($r->value_max / $div, 2));

        return ['times' => $times, 'unit' => $unit, 'series' => [$this->series($name, $color, true, $values, $peaks, $current !== null ? round((float) $current, 2) : null)], 'noP95' => $last];
    }

    /** One line per sensor of a class (all temperatures, all fans …). */
    protected function deviceSensors(NetworkSwitch $device, array $w, string $class): array
    {
        $sensors = DeviceSensor::where('network_switch_id', $device->id)->where('class', $class)->orderBy('name')->get();
        $rows = $sensors->isEmpty() ? collect() : DB::table('device_metrics')->where('network_switch_id', $device->id)
            ->whereIn('metric', $sensors->map->metric())->where('res', $w['res'])->whereBetween('at', [$w['first'], $w['last']])->where('samples', '>', 0)
            ->get()->groupBy('metric');
        $times = $this->align($w, collect(), fn () => null)[0];
        $series = [];
        foreach ($sensors->values() as $i => $sensor) {
            $mine = ($rows[$sensor->metric()] ?? collect())->keyBy('at');
            [$times, $values, $peaks] = $this->align($w, $mine, fn ($r) => round($r->value_sum / $r->samples / 100, 2), fn ($r) => round($r->value_max / 100, 2));
            $fresh = $sensor->value_at && $sensor->value_at->gt(now()->subSeconds(self::CURRENT_FOR));
            $series[] = $this->series($sensor->name, self::PALETTE[$i % count(self::PALETTE)], false, $values, $peaks, $fresh ? $sensor->value : null);
        }

        return ['times' => $times, 'unit' => DeviceSensor::CLASSES[$class][2] ?? $class, 'series' => $series, 'empty' => 'This device reports no ' . strtolower(DeviceSensor::CLASSES[$class][0] ?? $class) . ' sensors over SNMP.'];
    }

    // ---- Helpers ------------------------------------------------------------

    /**
     * One value (and peak) per bucket of the window, null where nothing was recorded.
     *
     * @return array{0: list<int>, 1: list<?float>, 2: ?list<?float>}
     */
    protected function align(array $w, Collection $rows, callable $value, ?callable $peak = null): array
    {
        $step = $w['res'] * 60;
        $times = $values = $peaks = [];
        for ($t = $w['first']; $t <= $w['last']; $t += $step) {
            $r = $rows[$t] ?? null;
            $times[] = $t;
            $values[] = $r ? $value($r) : null;
            $peaks[] = $r && $peak ? $peak($r) : null;
        }

        return [$times, $values, $peak ? $peaks : null];
    }

    protected function series(string $name, string $color, bool $fill, array $values, ?array $peaks, $current, ?int $total = null): array
    {
        $have = array_values(array_filter($values, fn ($v) => $v !== null));
        $peakHave = $peaks ? array_values(array_filter($peaks, fn ($v) => $v !== null)) : [];

        return [
            'name' => $name,
            'color' => $color,
            'fill' => $fill,
            'values' => $values,
            'peaks' => $peaks,
            'legend' => [
                'current' => $current,
                'average' => $have ? round(array_sum($have) / count($have), 3) : null,
                'maximum' => $peakHave ? max($peakHave) : ($have ? max($have) : null),
                'p95' => null,
                'total' => $have ? $total : null,
            ],
        ];
    }

    /** Short runs of nulls between two readings: drawn as a straight line between them. */
    protected function fillGaps(array $values, int $max = self::HEARTBEAT): array
    {
        $n = count($values);
        for ($i = 1; $i < $n; $i++) {
            if ($values[$i] !== null || $values[$i - 1] === null) {
                continue;
            }
            $j = $i;
            while ($j < $n && $values[$j] === null) {
                $j++;
            }
            if ($j < $n && $j - $i <= $max) {
                $a = $values[$i - 1];
                $b = $values[$j];
                for ($k = $i; $k < $j; $k++) {
                    $values[$k] = round($a + ($b - $a) * ($k - $i + 1) / ($j - $i + 1), 3);
                }
            }
            $i = $j;
        }

        return $values;
    }

    /** Drop the newest one or two minutes while no poll has filled them yet. */
    protected function trimUnpolled(array &$out): void
    {
        for ($k = 0; $k < 2 && count($out['times']) > 2; $k++) {
            $last = count($out['times']) - 1;
            if ($out['times'][$last] < time() - 180) {
                return;
            }
            foreach ($out['series'] as $s) {
                if ($s['values'][$last] !== null) {
                    return;
                }
            }
            array_pop($out['times']);
            foreach ($out['series'] as &$s) {
                array_pop($s['values']);
                if ($s['peaks'] !== null) {
                    array_pop($s['peaks']);
                }
            }
            unset($s);
        }
    }

    protected function p95(array $values): ?float
    {
        $have = array_values(array_filter($values, fn ($v) => $v !== null));
        if (! $have) {
            return null;
        }
        sort($have);

        return $have[max(0, (int) ceil(count($have) * 0.95) - 1)];
    }

    /**
     * Merge points: into fixed time buckets of $size seconds, or every $n
     * points. Values average (those present), peaks keep the highest.
     *
     * @return array{0: list<int>, 1: list<?float>, 2: ?list<?float>}
     */
    protected function regroup(array $times, array $values, ?array $peaks, ?int $size = null, ?int $n = null): array
    {
        $groups = [];
        foreach ($times as $i => $t) {
            $key = $size ? intdiv($t, $size) * $size : $times[intdiv($i, $n) * $n];
            $groups[$key][] = $i;
        }
        $outT = $outV = $outP = [];
        foreach ($groups as $t => $idx) {
            $outT[] = $t;
            $v = array_values(array_filter(array_map(fn ($i) => $values[$i] ?? null, $idx), fn ($x) => $x !== null));
            $outV[] = $v ? round(array_sum($v) / count($v), 3) : null;
            if ($peaks !== null) {
                $p = array_values(array_filter(array_map(fn ($i) => $peaks[$i] ?? null, $idx), fn ($x) => $x !== null));
                $outP[] = $p ? max($p) : null;
            }
        }

        return [$outT, $outV, $peaks !== null ? $outP : null];
    }
}
