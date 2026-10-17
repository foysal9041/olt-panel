<?php

namespace App\Http\Controllers;

use App\Models\GraphDashboard;
use App\Models\GraphWidget;
use App\Models\NetworkSwitch;
use App\Models\SwitchPort;
use App\Models\SwitchPortTraffic;
use App\Services\PortTrafficGraph;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Graph dashboards (as in LibreNMS): pages of traffic graphs a user builds
 * from interfaces of the switches they can see, shared with roles.
 * Opening a dashboard shows all its graphs — sharing it is the grant.
 */
class GraphDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $dashboards = GraphDashboard::visibleTo($user)->with(['owner:id,name', 'roles'])->withCount('widgets')->orderBy('name')->get();

        // As in LibreNMS, "Graph Dashboards" opens a dashboard straight away: the last one seen, else the first.
        if (! $request->boolean('list') && $dashboards->isNotEmpty()) {
            $last = $dashboards->firstWhere('id', (int) session('graph_dashboard'));

            return redirect()->route('graphs.show', $last ?? $dashboards->first());
        }

        return view('graphs.index', ['dashboards' => $dashboards, 'user' => $user]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
        ]);

        $dashboard = GraphDashboard::create($data + ['user_id' => $request->user()->id, 'visibility' => 'private']);

        return redirect()->route('graphs.show', [$dashboard, 'edit' => 1])
            ->with('success', "Dashboard \"{$dashboard->name}\" made — add graphs with \"Add graph\".");
    }

    public function show(Request $request, GraphDashboard $dashboard, PortTrafficGraph $graphs)
    {
        $user = $request->user();
        $dashboard->load(['roles', 'owner:id,name']);
        abort_unless($dashboard->canView($user), 403, 'This dashboard is not shared with you.');

        $widgets = $dashboard->widgets()->get();
        $ports = SwitchPort::with('networkSwitch:id,name')->whereIn('id', $widgets->flatMap->portIds()->unique())->get()->keyBy('id');
        foreach ($widgets as $widget) {
            $widget->setRelation('portList', collect($widget->portIds())->map(fn ($id) => $ports[$id] ?? null)->filter()->values());
            if ($widget->kind === 'top') {
                $widget->setRelation('topPorts', $graphs->top($widget->switchIds(), $widget->limit()));
            }
            if ($widget->kind === 'cpu') {
                $widget->setRelation('cpuDevice', $widget->device());
            }
        }

        $canEdit = $dashboard->canEdit($user);
        $editing = $canEdit && $request->boolean('edit');
        session(['graph_dashboard' => $dashboard->id]);

        return view('graphs.show', [
            'dashboard' => $dashboard,
            'widgets' => $widgets,
            'canEdit' => $canEdit,
            'canManage' => $dashboard->canManage($user),
            'editing' => $editing,
            'switches' => $canEdit ? $this->switchOptions($user) : collect(),
            // Interfaces already on the dashboard from switches this editor can't see: kept, not offered.
            'otherPorts' => $canEdit ? $ports->reject(fn ($p) => NetworkSwitch::visibleTo($user)->whereKey($p->network_switch_id)->exists())->values() : collect(),
            'graph' => array_key_exists($request->query('graph'), SwitchPortTraffic::GRAPHS) ? $request->query('graph') : null,
            'allDashboards' => GraphDashboard::visibleTo($user)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Name, description, defaults and who it is shared with. */
    public function update(Request $request, GraphDashboard $dashboard)
    {
        abort_unless($dashboard->canManage($request->user()), 403, 'Only the owner or an admin can change this dashboard\'s settings.');

        $roles = array_keys(config('roles'));
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'visibility' => ['required', Rule::in(array_keys(GraphDashboard::VISIBILITY))],
            'default_graph' => ['required', Rule::in(array_keys(SwitchPortTraffic::GRAPHS))],
            'refresh_seconds' => ['required', 'integer', Rule::in(GraphDashboard::REFRESH)],
            'roles' => 'nullable|array',
            'roles.*' => 'in:none,view,edit',
        ]);
        $shares = collect($data['roles'] ?? [])->only($roles)->reject(fn ($v) => $v === 'none');
        if ($data['visibility'] === 'roles' && $shares->isEmpty()) {
            return back()->withInput()->with('error', 'Pick at least one role to share with, or choose another "Who can open it".');
        }

        DB::transaction(function () use ($dashboard, $data, $shares) {
            $dashboard->update(collect($data)->except('roles')->all());
            $dashboard->syncShares($data['visibility'], $shares->all());
        });

        return back()->with('success', 'Dashboard settings saved.');
    }

    public function destroy(Request $request, GraphDashboard $dashboard)
    {
        abort_unless($dashboard->canManage($request->user()), 403);
        $dashboard->delete();

        return redirect()->route('graphs.index')->with('success', "Dashboard \"{$dashboard->name}\" deleted.");
    }

    public function storeWidget(Request $request, GraphDashboard $dashboard)
    {
        abort_unless($dashboard->canEdit($request->user()), 403);
        $data = $this->validatedWidget($request, null);

        $dashboard->widgets()->create($data + ['position' => (int) $dashboard->widgets()->max('position') + 1]);

        return redirect()->route('graphs.show', [$dashboard, 'edit' => 1])->with('success', 'Graph added.');
    }

    public function updateWidget(Request $request, GraphDashboard $dashboard, GraphWidget $widget)
    {
        abort_unless($widget->graph_dashboard_id === $dashboard->id, 404);
        abort_unless($dashboard->canEdit($request->user()), 403);

        $widget->update($this->validatedWidget($request, $widget));

        return redirect()->route('graphs.show', [$dashboard, 'edit' => 1])->with('success', 'Graph saved.');
    }

    public function destroyWidget(Request $request, GraphDashboard $dashboard, GraphWidget $widget)
    {
        abort_unless($widget->graph_dashboard_id === $dashboard->id, 404);
        abort_unless($dashboard->canEdit($request->user()), 403);
        $widget->delete();

        return redirect()->route('graphs.show', [$dashboard, 'edit' => 1])->with('success', 'Graph removed.');
    }

    /** One place up or down. */
    public function moveWidget(Request $request, GraphDashboard $dashboard, GraphWidget $widget)
    {
        abort_unless($widget->graph_dashboard_id === $dashboard->id, 404);
        abort_unless($dashboard->canEdit($request->user()), 403);

        $ids = $dashboard->widgets()->pluck('id')->all();
        $at = array_search($widget->id, $ids, true);
        $to = $request->input('dir') === 'up' ? $at - 1 : $at + 1;
        if ($at !== false && isset($ids[$to])) {
            [$ids[$at], $ids[$to]] = [$ids[$to], $ids[$at]];
            DB::transaction(function () use ($ids) {
                foreach ($ids as $i => $id) {
                    GraphWidget::whereKey($id)->update(['position' => $i + 1]);
                }
            });
        }

        return redirect()->route('graphs.show', [$dashboard, 'edit' => 1]);
    }

    /** A traffic graph's points and legend (public/js/traffic-graph.js). */
    public function data(Request $request, GraphDashboard $dashboard, GraphWidget $widget, \App\Services\GraphEngine $engine)
    {
        abort_unless($widget->graph_dashboard_id === $dashboard->id && in_array($widget->kind, ['traffic', 'cpu'], true), 404);
        $dashboard->load('roles');
        abort_unless($dashboard->canView($request->user()), 403);

        $key = array_key_exists($request->query('graph'), SwitchPortTraffic::GRAPHS) ? $request->query('graph') : ($widget->graph ?: $dashboard->default_graph);
        $points = $request->integer('points') ?: null;

        $avg = $request->integer('avg') === 5 ? 5 : 1;
        $points = $points ? max(60, min($points, 2000)) : null;
        $window = $engine->window($key, $request->integer('from') ?: null, $request->integer('to') ?: null, $avg);

        if ($widget->kind === 'cpu') {
            $device = $widget->device();
            abort_unless($device, 404);
            return response()->json($engine->build('device_' . $widget->metric(), $device, $window, $points));
        }

        $ports = $widget->ports();

        return response()->json($ports->count() === 1 ? $engine->build('port_bits', $ports->first(), $window, $points) : $engine->build('ports_bits', $ports, $window, $points));
    }

    /** "Add to dashboard" from a port page: a half-width graph of that port. */
    public function addPort(Request $request)
    {
        $data = $request->validate([
            'graph_dashboard_id' => 'required|exists:graph_dashboards,id',
            'switch_port_id' => 'required|exists:switch_ports,id',
        ]);
        $dashboard = GraphDashboard::with('roles')->findOrFail($data['graph_dashboard_id']);
        $port = SwitchPort::with('networkSwitch')->findOrFail($data['switch_port_id']);
        abort_unless($dashboard->canEdit($request->user()) && $request->user()->canSeeSwitch($port->networkSwitch), 403);

        $dashboard->widgets()->create([
            'kind' => 'traffic',
            'width' => 6,
            'settings' => ['ports' => [$port->id]],
            'position' => (int) $dashboard->widgets()->max('position') + 1,
        ]);

        return back()->with('success', "{$port->label} added to \"{$dashboard->name}\".")->with('dashboard_link', route('graphs.show', $dashboard));
    }

    /**
     * Validated fields of the add / edit graph form. Interfaces must be on
     * switches the editor can see — or already on this graph (someone with
     * wider access put them there).
     */
    protected function validatedWidget(Request $request, ?GraphWidget $widget): array
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(GraphWidget::KINDS))],
            'title' => 'nullable|string|max:150',
            'width' => ['required', Rule::in(array_keys(GraphWidget::WIDTHS))],
            'graph' => ['nullable', Rule::in(array_keys(SwitchPortTraffic::GRAPHS))],
            'ports' => 'nullable|array|max:64',
            'ports.*' => 'integer',
            'switches' => 'nullable|array',
            'switches.*' => 'integer',
            'limit' => 'nullable|integer|min:3|max:25',
            'device' => 'nullable|integer',
            'mirror' => 'nullable|boolean',
            'metric' => ['nullable', Rule::in(GraphWidget::DEVICE_METRICS)],
        ]);

        $user = $request->user();
        $visibleSwitches = NetworkSwitch::visibleTo($user)->pluck('id');
        $kept = $widget ? $widget->portIds() : [];

        if ($data['kind'] === 'traffic') {
            $asked = array_map('intval', $data['ports'] ?? []);
            $allowed = SwitchPort::whereIn('id', $asked)
                ->where(fn ($q) => $q->whereIn('network_switch_id', $visibleSwitches)->orWhereIn('id', $kept))
                ->pluck('id')->all();
            $ports = array_values(array_filter($asked, fn ($id) => in_array($id, $allowed, true)));
            if (! $ports) {
                throw \Illuminate\Validation\ValidationException::withMessages(['ports' => 'Pick at least one interface.']);
            }
            $settings = ['ports' => $ports, 'mirror' => $request->boolean('mirror')];
        } elseif ($data['kind'] === 'cpu') {
            $device = (int) ($data['device'] ?? 0);
            if (! $visibleSwitches->contains($device) && $device !== $widget?->deviceId()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['device' => 'Pick a switch or router you can see.']);
            }
            $settings = ['device' => $device, 'metric' => $data['metric'] ?? 'cpu'];
        } else {
            $settings = [
                'switches' => array_values(array_intersect(array_map('intval', $data['switches'] ?? []), $visibleSwitches->all())),
                'limit' => (int) ($data['limit'] ?? 10),
            ];
        }

        return [
            'kind' => $data['kind'],
            'title' => filled($data['title'] ?? null) ? trim($data['title']) : null,
            'width' => (int) $data['width'],
            'graph' => $data['graph'] ?? null,
            'settings' => $settings,
        ];
    }

    /** Switches the editor can see, each with its interfaces that have traffic counters. */
    protected function switchOptions($user)
    {
        return NetworkSwitch::visibleTo($user)
            ->with(['ports' => fn ($q) => $q->whereNotNull('counter_bits')])
            ->orderBy('name')
            ->get(['id', 'name', 'ip', 'device_type']);
    }
}
