<?php

namespace App\Http\Controllers;

use App\Models\NetworkSwitch;
use App\Models\NocAlertSetting;
use App\Models\SwitchPort;
use App\Models\Zone;
use App\Services\SwitchPoller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SwitchController extends Controller
{
    public function index()
    {
        $switches = NetworkSwitch::withCount([
            'ports',
            'ports as ports_up_count' => fn ($q) => $q->where('oper_status', SwitchPort::UP),
            'ports as ports_sfp_count' => fn ($q) => $q->whereNotNull('rx_power'),
            'ports as ports_rx_alarm_count' => fn ($q) => $q->where('rx_alarm', true),
        ])->orderBy('name')->get();

        $summary = [
            'total' => $switches->count(),
            'up' => $switches->where('status', 1)->count(),
            'down' => $switches->where('status', 0)->count(),
            'ports_down' => SwitchPort::where('admin_status', 1)->where('oper_status', '!=', SwitchPort::UP)->count(),
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

        return $this->pollAndRedirect($switch, $poller, 'Switch Added');
    }

    public function show(NetworkSwitch $switch)
    {
        $ports = $switch->ports()->get();
        $events = $switch->events()->with('port')->latest('occurred_at')->limit(25)->get();
        $rxThreshold = NocAlertSetting::current()->rx_low_threshold;

        return view('switches.show', compact('switch', 'ports', 'events', 'rxThreshold'));
    }

    public function edit(NetworkSwitch $switch)
    {
        return view('switches.edit', [
            'switch' => $switch,
            'zones' => Zone::orderBy('name')->pluck('name'),
        ]);
    }

    public function update(Request $request, NetworkSwitch $switch, SwitchPoller $poller)
    {
        $switch->update($this->validated($request, $switch));

        return $this->pollAndRedirect($switch, $poller, 'Switch Updated');
    }

    public function destroy(NetworkSwitch $switch)
    {
        $switch->delete();

        return redirect()
            ->route('switches.index')
            ->with('success', 'Switch Deleted Successfully');
    }

    public function poll(NetworkSwitch $switch, SwitchPoller $poller)
    {
        return $this->pollAndRedirect($switch, $poller, 'Poll finished', back: true);
    }

    public function togglePortNotify(NetworkSwitch $switch, SwitchPort $port)
    {
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
