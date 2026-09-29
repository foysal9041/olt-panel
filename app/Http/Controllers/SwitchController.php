<?php

namespace App\Http\Controllers;

use App\Models\NetworkSwitch;
use App\Models\NocAlertSetting;
use App\Models\SwitchPort;
use App\Models\SwitchPortReading;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;
use App\Services\SwitchPoller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SwitchController extends Controller
{
    public function index()
    {
        $switches = NetworkSwitch::visibleTo(auth()->user())->withCount([
            'ports',
            'ports as ports_up_count' => fn ($q) => $q->where('oper_status', SwitchPort::UP),
            'ports as ports_sfp_count' => fn ($q) => $q->whereNotNull('rx_power'),
            'ports as ports_rx_alarm_count' => fn ($q) => $q->where('rx_alarm', true),
        ])->orderBy('name')->get();

        $summary = [
            'total' => $switches->count(),
            'up' => $switches->where('status', 1)->count(),
            'down' => $switches->where('status', 0)->count(),
            'ports_down' => SwitchPort::whereIn('network_switch_id', $switches->pluck('id'))->where('admin_status', 1)->where('oper_status', '!=', SwitchPort::UP)->count(),
        ];

        return view('switches.index', compact('switches', 'summary'));
    }

    public function create()
    {
        return view('switches.create', [
            'switch' => new NetworkSwitch(['vendor' => 'generic', 'snmp_version' => '2c', 'snmp_port' => 161, 'is_active' => true, 'notify' => true, 'dom_divisor' => 1, 'dom_power_unit' => 'dbm']),
            'zones' => Zone::orderBy('name')->pluck('name'),
        ]);
    }

    public function store(Request $request, SwitchPoller $poller)
    {
        $switch = NetworkSwitch::create($this->validated($request));

        if (auth()->user()->deviceAccess('switch') === 'selected') {
            auth()->user()->allowedSwitches()->syncWithoutDetaching([$switch->id]);
        }

        return $this->pollAndRedirect($switch, $poller, 'Switch Added');
    }

    /** Port history graph ranges: label, span in seconds, bucket size. */
    public const HISTORY_RANGES = [
        '24h' => ['24 Hours', 86400, 300],
        '7d' => ['7 Days', 7 * 86400, 1800],
        '30d' => ['30 Days', 30 * 86400, 7200],
        '90d' => ['90 Days', 90 * 86400, 21600],
    ];

    public function show(NetworkSwitch $switch)
    {
        abort_unless(auth()->user()->canSeeSwitch($switch), 403);

        $ports = $switch->ports()->get();
        $events = $switch->events()->with('port')->latest('occurred_at')->limit(25)->get();
        $settings = NocAlertSetting::current();
        $rxTrend = $this->rxTrend($ports->filter(fn ($p) => $p->rx_power !== null));

        return view('switches.show', compact('switch', 'ports', 'events', 'settings', 'rxTrend'));
    }

    public function portHistory(NetworkSwitch $switch, SwitchPort $port)
    {
        abort_unless(auth()->user()->canSeeSwitch($switch), 403);

        abort_unless($port->network_switch_id === $switch->id, 404);

        $events = $switch->events()->where('switch_port_id', $port->id)->latest('occurred_at')->limit(30)->get();
        $warning = $port->rxWarning(NocAlertSetting::current());
        $firstReading = $port->readings()->min('recorded_at');
        $ranges = self::HISTORY_RANGES;

        return view('switches.port', compact('switch', 'port', 'events', 'warning', 'firstReading', 'ranges'));
    }

    /**
     * Bucketed Rx/Tx history for one port.
     *
     * { start, end, step, points: [[ts, rxAvg, rxMin, rxMax, txAvg]], stats: {...} }
     */
    public function portHistoryData(Request $request, NetworkSwitch $switch, SwitchPort $port)
    {
        abort_unless(auth()->user()->canSeeSwitch($switch), 403);

        abort_unless($port->network_switch_id === $switch->id, 404);

        [, $span, $step] = self::HISTORY_RANGES[$request->query('range')] ?? self::HISTORY_RANGES['24h'];

        $end = now();
        $start = $end->copy()->subSeconds($span);

        $points = DB::table('switch_port_readings')
            ->where('switch_port_id', $port->id)
            ->where('recorded_at', '>=', $start)
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get([
                DB::raw("FLOOR(UNIX_TIMESTAMP(recorded_at) / {$step}) * {$step} AS bucket"),
                DB::raw('AVG(rx_power) AS rx'),
                DB::raw('MIN(rx_power) AS rx_min'),
                DB::raw('MAX(rx_power) AS rx_max'),
                DB::raw('AVG(tx_power) AS tx'),
            ])
            ->map(fn ($r) => [
                (int) $r->bucket,
                $r->rx === null ? null : round((float) $r->rx, 2),
                $r->rx_min === null ? null : round((float) $r->rx_min, 2),
                $r->rx_max === null ? null : round((float) $r->rx_max, 2),
                $r->tx === null ? null : round((float) $r->tx, 2),
            ]);

        $range = SwitchPortReading::where('switch_port_id', $port->id)
            ->where('recorded_at', '>=', $start)
            ->whereNotNull('rx_power');

        $first = (clone $range)->orderBy('recorded_at')->first(['rx_power', 'recorded_at']);
        $last = (clone $range)->orderByDesc('recorded_at')->first(['rx_power', 'recorded_at']);
        $agg = (clone $range)->selectRaw('MIN(rx_power) AS min, MAX(rx_power) AS max, AVG(rx_power) AS avg')->first();

        // Biggest fall between two neighbouring buckets — "when did it drop?"
        $biggestDrop = null;
        $prev = null;
        foreach ($points as $p) {
            if ($p[1] === null) {
                continue;
            }
            if ($prev !== null && ($drop = $p[1] - $prev[1]) < 0 && ($biggestDrop === null || $drop < $biggestDrop['change'])) {
                $biggestDrop = ['change' => round($drop, 2), 'from' => $prev[1], 'to' => $p[1], 'at' => $p[0]];
            }
            $prev = $p;
        }

        return response()->json([
            'start' => $start->getTimestamp(),
            'end' => $end->getTimestamp(),
            'step' => $step,
            'points' => $points,
            'stats' => [
                'first' => $first?->rx_power,
                'first_at' => $first?->recorded_at?->getTimestamp(),
                'last' => $last?->rx_power,
                'last_at' => $last?->recorded_at?->getTimestamp(),
                'change' => $first && $last ? round($last->rx_power - $first->rx_power, 2) : null,
                'min' => $agg?->min === null ? null : round((float) $agg->min, 2),
                'max' => $agg?->max === null ? null : round((float) $agg->max, 2),
                'avg' => $agg?->avg === null ? null : round((float) $agg->avg, 2),
                'biggest_drop' => $biggestDrop,
            ],
            'thresholds' => (function () use ($port) {
                $warning = $port->rxWarning(NocAlertSetting::current());

                return [
                    'warn' => $warning['value'],
                    'warn_label' => $warning['label'],
                    'low_alarm' => $port->rx_low_alarm,
                    'high_warn' => $port->rx_high_warn,
                    'high_alarm' => $port->rx_high_alarm,
                ];
            })(),
        ]);
    }

    /**
     * Rx change over the last 24h for each port: now vs. the average around
     * 24h ago (or the oldest reading, if history is younger than a day).
     *
     * @return array<int, array{change: float, from: float, since: \Illuminate\Support\Carbon}>
     */
    protected function rxTrend($ports): array
    {
        if ($ports->isEmpty()) {
            return [];
        }

        $ids = $ports->pluck('id');
        $dayAgo = now()->subDay();

        $then = SwitchPortReading::whereIn('switch_port_id', $ids)
            ->whereBetween('recorded_at', [$dayAgo->copy()->subMinutes(15), $dayAgo->copy()->addMinutes(15)])
            ->whereNotNull('rx_power')
            ->groupBy('switch_port_id')
            ->selectRaw('switch_port_id, AVG(rx_power) AS rx, MIN(recorded_at) AS at')
            ->get()
            ->keyBy('switch_port_id');

        $missing = $ids->diff($then->keys());

        if ($missing->isNotEmpty()) {
            $oldest = SwitchPortReading::whereIn('switch_port_id', $missing)
                ->where('recorded_at', '>=', $dayAgo)
                ->whereNotNull('rx_power')
                ->whereIn('id', SwitchPortReading::selectRaw('MIN(id)')
                    ->whereIn('switch_port_id', $missing)
                    ->where('recorded_at', '>=', $dayAgo)
                    ->whereNotNull('rx_power')
                    ->groupBy('switch_port_id'))
                ->get(['switch_port_id', 'rx_power as rx', 'recorded_at as at'])
                ->keyBy('switch_port_id');

            $then = $then->union($oldest);
        }

        $trend = [];

        foreach ($ports as $port) {
            if (! isset($then[$port->id])) {
                continue;
            }

            $from = round((float) $then[$port->id]->rx, 2);

            $trend[$port->id] = [
                'change' => round($port->rx_power - $from, 2),
                'from' => $from,
                'since' => \Illuminate\Support\Carbon::parse($then[$port->id]->at),
            ];
        }

        return $trend;
    }

    public function edit(NetworkSwitch $switch)
    {
        abort_unless(auth()->user()->canSeeSwitch($switch), 403);

        return view('switches.edit', [
            'switch' => $switch,
            'zones' => Zone::orderBy('name')->pluck('name'),
        ]);
    }

    public function update(Request $request, NetworkSwitch $switch, SwitchPoller $poller)
    {
        abort_unless(auth()->user()->canSeeSwitch($switch), 403);

        $switch->update($this->validated($request, $switch));

        return $this->pollAndRedirect($switch, $poller, 'Switch Updated');
    }

    public function destroy(NetworkSwitch $switch)
    {
        abort_unless(auth()->user()->canSeeSwitch($switch), 403);

        $switch->delete();

        return redirect()
            ->route('switches.index')
            ->with('success', 'Switch Deleted Successfully');
    }

    public function poll(NetworkSwitch $switch, SwitchPoller $poller)
    {
        abort_unless(auth()->user()->canSeeSwitch($switch), 403);

        return $this->pollAndRedirect($switch, $poller, 'Poll finished', back: true);
    }

    public function togglePortNotify(NetworkSwitch $switch, SwitchPort $port)
    {
        abort_unless(auth()->user()->canSeeSwitch($switch), 403);

        abort_unless($port->network_switch_id === $switch->id, 404);

        $port->update(['notify' => ! $port->notify]);

        return back()->with('success', "Alerts " . ($port->notify ? 'enabled' : 'muted') . " for {$port->label}");
    }

    protected function pollAndRedirect(NetworkSwitch $switch, SwitchPoller $poller, string $prefix, bool $back = false)
    {
        $result = $poller->poll($switch);

        $redirect = $back ? back() : redirect()->route('switches.show', $switch);

        if (! $result['ok']) {
            return $redirect->with('error', "{$prefix}, but SNMP polling failed: {$result['error']}");
        }

        return $redirect->with('success', "{$prefix}. Found {$result['ports']} ports and {$result['transceivers']} transceivers.");
    }

    protected function validated(Request $request, ?NetworkSwitch $switch = null): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ip' => ['required', 'ip', Rule::unique('network_switches', 'ip')->ignore($switch?->id)],
            'zone' => 'nullable|exists:zones,name',
            'vendor' => ['required', Rule::in(array_keys(NetworkSwitch::VENDORS))],
            'snmp_version' => 'required|in:1,2c',
            // Blank on edit keeps the stored community.
            'community' => [$switch ? 'nullable' : 'required', 'string', 'max:255'],
            'snmp_port' => 'required|integer|min:1|max:65535',
            'dom_rx_oid' => ['nullable', 'regex:/^\.?\d+(\.\d+)+$/', 'max:255'],
            'dom_tx_oid' => ['nullable', 'regex:/^\.?\d+(\.\d+)+$/', 'max:255'],
            'dom_temp_oid' => ['nullable', 'regex:/^\.?\d+(\.\d+)+$/', 'max:255'],
            'dom_divisor' => 'required|integer|min:1|max:1000000',
            'dom_power_unit' => 'required|in:dbm,mw,uw',
        ], [
            'ip.unique' => 'A switch with this IP address already exists.',
            'dom_rx_oid.regex' => 'OIDs must be numeric, e.g. 1.3.6.1.4.1.3320.9.63.1.7.1.3',
            'dom_tx_oid.regex' => 'OIDs must be numeric, e.g. 1.3.6.1.4.1.3320.9.63.1.7.1.4',
            'dom_temp_oid.regex' => 'OIDs must be numeric, e.g. 1.3.6.1.4.1.3320.9.63.1.7.1.2',
        ]);

        if ($switch && blank($validated['community'])) {
            unset($validated['community']);
        }

        $validated['is_active'] = $request->boolean('is_active');
        $validated['notify'] = $request->boolean('notify');

        return $validated;
    }
}
