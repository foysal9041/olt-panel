<?php

namespace App\Http\Controllers;

use App\Models\NetworkSwitch;
use App\Models\SwitchPort;
use App\Services\GraphEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Any port or device graph (LibreNMS style): the data (JSON, or CSV to
 * download) and the full graph page — periods, a custom From–To range,
 * drag-to-zoom, export, and the Day / Week / Month / Year thumbnails.
 */
class GraphController extends Controller
{
    public function data(Request $request, GraphEngine $engine)
    {
        [$type, $subject] = $this->subject($request);
        $window = $engine->window($request->query('graph'), $this->time($request->query('from')), $this->time($request->query('to')), $request->integer('avg') === 5 ? 5 : 1);
        $points = $request->integer('points');
        $csv = $request->query('format') === 'csv';
        $graph = $engine->build($type, $subject, $window, $csv || ! $points ? null : max(60, min($points, 2000)));

        if ($csv) {
            $name = preg_replace('/[^\w.-]+/', '_', $this->label($subject) . '_' . $type . '_' . date('Ymd-Hi', $window['from']) . '-' . date('Ymd-Hi', $window['to'])) . '.csv';

            return response($engine->csv($graph), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => "attachment; filename=\"{$name}\""]);
        }

        return response()->json($graph);
    }

    public function show(Request $request)
    {
        [$type, $subject] = $this->subject($request);
        $device = $subject instanceof SwitchPort ? $subject->networkSwitch : $subject;
        $related = $subject instanceof SwitchPort ? GraphEngine::PORT_GRAPHS : $device->graphTypes();

        return view('graph.show', [
            'type' => $type,
            'subject' => $subject,
            'device' => $device,
            'port' => $subject instanceof SwitchPort ? $subject : null,
            'related' => $related,
            'graph' => array_key_exists($request->query('graph'), \App\Models\SwitchPortTraffic::GRAPHS) ? $request->query('graph') : 'day',
            'from' => $this->time($request->query('from')),
            'to' => $this->time($request->query('to')),
        ]);
    }

    /** The graph type and what it is of (a port or a device), checked against device access. */
    protected function subject(Request $request): array
    {
        $type = (string) $request->query('type');
        abort_unless(isset(GraphEngine::TYPES[$type]) && GraphEngine::TYPES[$type][1] !== 'ports', 404);
        $id = $request->integer('id');

        if (GraphEngine::TYPES[$type][1] === 'port') {
            $port = SwitchPort::with('networkSwitch')->findOrFail($id);
            abort_unless($port->networkSwitch && auth()->user()->canSeeSwitch($port->networkSwitch), 403);

            return [$type, $port];
        }

        $device = NetworkSwitch::findOrFail($id);
        abort_unless(auth()->user()->canSeeSwitch($device), 403);

        return [$type, $device];
    }

    /** Epoch seconds, or a date like 2026-10-17T13:00 in the panel's time zone. */
    protected function time($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        try {
            return Carbon::parse($value, config('app.timezone'))->timestamp;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function label($subject): string
    {
        return $subject instanceof SwitchPort ? ($subject->networkSwitch?->name . '_' . $subject->label) : $subject->name;
    }
}
