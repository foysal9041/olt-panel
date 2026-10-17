<?php

namespace App\Http\Controllers;

use App\Models\NetworkSwitch;
use App\Models\SwitchPort;
use App\Models\SwitchPortTraffic;
use Illuminate\Http\Request;

/**
 * Cacti-style traffic graphs: every port of a switch on one page, and the
 * graph data (Daily / Weekly / Monthly / Yearly) for a port.
 */
class PortTrafficController extends Controller
{
    /** All the switch's ports that carry traffic, busiest first, each with its graph. */
    public function index(Request $request, NetworkSwitch $switch)
    {
        abort_unless(auth()->user()->canSeeSwitch($switch), 403);

        $type = in_array($request->query('type'), \App\Services\GraphEngine::PORT_GRAPHS, true) ? $request->query('type') : 'port_bits';
        $ports = $switch->ports()->whereNotNull('counter_bits')->get()
            ->sortByDesc(fn (SwitchPort $p) => [$p->oper_status === SwitchPort::UP, ($p->in_bps ?? 0) + ($p->out_bps ?? 0)])
            ->values();

        return view('switches.traffic', ['switch' => $switch, 'ports' => $ports, 'type' => $type]);
    }

    /** CPU graph data of a switch or router. */
    public function cpu(Request $request, NetworkSwitch $switch, \App\Services\GraphEngine $engine)
    {
        abort_unless(auth()->user()->canSeeSwitch($switch), 403);

        return response()->json($engine->build('device_cpu', $switch, $engine->window($request->query('graph'), null, null, $this->avg($request)), $this->points($request)));
    }

    public function data(Request $request, NetworkSwitch $switch, SwitchPort $port, \App\Services\GraphEngine $engine)
    {
        abort_unless(auth()->user()->canSeeSwitch($switch), 403);
        abort_unless($port->network_switch_id === $switch->id, 404);

        return response()->json($engine->build('port_bits', $port, $engine->window($request->query('graph'), null, null, $this->avg($request)), $this->points($request)));
    }

    protected function points(Request $request): ?int
    {
        $p = $request->integer('points');

        return $p ? max(60, min($p, 2000)) : null;
    }

    /** ?avg=5 — 5-minute averages on the 1-minute periods (else 1-minute). */
    protected function avg(Request $request): int
    {
        return $request->integer('avg') === 5 ? 5 : 1;
    }
}
