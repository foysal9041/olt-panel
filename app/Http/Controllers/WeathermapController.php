<?php

namespace App\Http\Controllers;

use App\Models\NetworkSwitch;
use App\Models\SwitchPort;
use App\Models\Weathermap;
use App\Models\WeathermapLink;
use App\Models\WeathermapNode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Weathermaps: nodes placed on a canvas (drag to move) and links coloured
 * by their interface's live traffic. Opening a map shows all of it —
 * sharing it is the grant; editors add devices and interfaces only from
 * switches and routers they can see.
 */
class WeathermapController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $maps = Weathermap::visibleTo($user)->with(['owner:id,name', 'roles'])->withCount(['nodes', 'links'])->orderBy('name')->get();

        return view('maps.index', ['maps' => $maps, 'user' => $user]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
        ]);
        $map = Weathermap::create($data + ['user_id' => $request->user()->id, 'visibility' => 'private']);

        return redirect()->route('maps.show', [$map, 'edit' => 1])->with('success', "Weathermap \"{$map->name}\" made — add nodes, then links between them.");
    }

    public function show(Request $request, Weathermap $map)
    {
        $user = $request->user();
        $map->load(['roles', 'owner:id,name', 'nodes.device', 'links.port.networkSwitch']);
        abort_unless($map->canView($user), 403, 'This weathermap is not shared with you.');

        $canEdit = $map->canEdit($user);
        $editing = $canEdit && $request->boolean('edit');
        $devices = $editing ? NetworkSwitch::visibleTo($user)->with(['ports' => fn ($q) => $q->whereNotNull('counter_bits')])->orderBy('name')->get(['id', 'name', 'ip', 'device_type', 'status']) : collect();

        return view('maps.show', [
            'map' => $map,
            'canEdit' => $canEdit,
            'canManage' => $map->canManage($user),
            'editing' => $editing,
            'devices' => $devices,
            // Interfaces already on links from devices this editor can't see: kept, not offered.
            'otherPorts' => $editing ? $map->links->pluck('port')->filter()->reject(fn ($p) => $devices->contains('id', $p->network_switch_id))->unique('id')->values() : collect(),
        ]);
    }

    public function update(Request $request, Weathermap $map)
    {
        abort_unless($map->canManage($request->user()), 403, 'Only the owner or an admin can change this map\'s settings.');
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'visibility' => ['required', Rule::in(array_keys(Weathermap::VISIBILITY))],
            'width' => 'required|integer|min:600|max:4000',
            'height' => 'required|integer|min:400|max:3000',
            'label_mode' => ['required', Rule::in(array_keys(Weathermap::LABEL_MODES))],
            'refresh_seconds' => ['required', 'integer', Rule::in(Weathermap::REFRESH)],
            'roles' => 'nullable|array',
            'roles.*' => 'in:none,view,edit',
        ]);
        $shares = collect($data['roles'] ?? [])->reject(fn ($v) => $v === 'none');
        if ($data['visibility'] === 'roles' && $shares->isEmpty()) {
            return back()->withInput()->with('error', 'Pick at least one role to share with, or choose another "Who can open it".');
        }

        DB::transaction(function () use ($map, $data, $shares) {
            $map->update(collect($data)->except('roles')->all());
            $map->syncShares($data['visibility'], $shares->all());
            // Keep every node on the (maybe smaller) canvas.
            $map->nodes()->where('x', '>', $map->width - 20)->update(['x' => $map->width - 60]);
            $map->nodes()->where('y', '>', $map->height - 20)->update(['y' => $map->height - 40]);
        });

        return back()->with('success', 'Weathermap settings saved.');
    }

    public function destroy(Request $request, Weathermap $map)
    {
        abort_unless($map->canManage($request->user()), 403);
        $map->delete();

        return redirect()->route('maps.index')->with('success', "Weathermap \"{$map->name}\" deleted.");
    }

    // ---- Nodes ------------------------------------------------------------

    public function storeNode(Request $request, Weathermap $map)
    {
        abort_unless($map->canEdit($request->user()), 403);
        $data = $this->validatedNode($request, $map, null);

        // New nodes go in a loose grid so they don't land on each other.
        $n = $map->nodes()->count();
        $data += ['x' => min($map->width - 80, 120 + ($n % 6) * 170), 'y' => min($map->height - 60, 90 + intdiv($n, 6) % 5 * 120)];
        $map->nodes()->create($data);

        return $this->backToEditor($map, 'Node added — drag it into place.');
    }

    public function updateNode(Request $request, Weathermap $map, WeathermapNode $node)
    {
        abort_unless($node->weathermap_id === $map->id, 404);
        abort_unless($map->canEdit($request->user()), 403);
        $node->update($this->validatedNode($request, $map, $node));

        return $this->backToEditor($map, 'Node saved.');
    }

    public function destroyNode(Request $request, Weathermap $map, WeathermapNode $node)
    {
        abort_unless($node->weathermap_id === $map->id, 404);
        abort_unless($map->canEdit($request->user()), 403);
        $node->delete();

        return $this->backToEditor($map, 'Node and its links removed.');
    }

    /** Drag-and-drop: new x / y of the nodes moved (JSON). */
    public function positions(Request $request, Weathermap $map)
    {
        abort_unless($map->canEdit($request->user()), 403);
        $data = $request->validate([
            'nodes' => 'required|array|max:500',
            'nodes.*.id' => 'required|integer',
            'nodes.*.x' => 'required|numeric',
            'nodes.*.y' => 'required|numeric',
        ]);

        $ids = $map->nodes()->pluck('id')->flip();
        foreach ($data['nodes'] as $n) {
            if (isset($ids[(int) $n['id']])) {
                WeathermapNode::whereKey((int) $n['id'])->update([
                    'x' => max(0, min($map->width, (int) round($n['x']))),
                    'y' => max(0, min($map->height, (int) round($n['y']))),
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }

    // ---- Links ------------------------------------------------------------

    public function storeLink(Request $request, Weathermap $map)
    {
        abort_unless($map->canEdit($request->user()), 403);
        $data = $this->validatedLink($request, $map, null);

        // The interface sits on the "to" device: its inbound is what flows from → to.
        if (! $request->has('reverse') && $data['switch_port_id']) {
            $port = SwitchPort::find($data['switch_port_id']);
            $nodes = $map->nodes()->whereIn('id', [$data['from_node_id'], $data['to_node_id']])->pluck('network_switch_id', 'id');
            $data['reverse'] = $port->network_switch_id === $nodes[$data['to_node_id']] && $port->network_switch_id !== $nodes[$data['from_node_id']];
        }
        $map->links()->create($data);

        return $this->backToEditor($map, 'Link added.');
    }

    public function updateLink(Request $request, Weathermap $map, WeathermapLink $link)
    {
        abort_unless($link->weathermap_id === $map->id, 404);
        abort_unless($map->canEdit($request->user()), 403);
        $link->update($this->validatedLink($request, $map, $link));

        return $this->backToEditor($map, 'Link saved.');
    }

    public function destroyLink(Request $request, Weathermap $map, WeathermapLink $link)
    {
        abort_unless($link->weathermap_id === $map->id, 404);
        abort_unless($map->canEdit($request->user()), 403);
        $link->delete();

        return $this->backToEditor($map, 'Link removed.');
    }

    /** Live state for the map's JavaScript: each link's traffic and each device's up / down. */
    public function data(Request $request, Weathermap $map)
    {
        $map->load(['roles', 'nodes.device', 'links.port.networkSwitch']);
        abort_unless($map->canView($request->user()), 403);

        return response()->json([
            'links' => $map->links->mapWithKeys(fn ($l) => [$l->id => $l->live() + [
                'abColour' => WeathermapLink::colour($l->live()['abPct']),
                'baColour' => WeathermapLink::colour($l->live()['baPct']),
            ]]),
            'nodes' => $map->nodes->mapWithKeys(fn ($n) => [$n->id => $n->state()]),
            'at' => now()->format('h:i:s A'),
        ]);
    }

    // ---- Helpers ----------------------------------------------------------

    protected function validatedNode(Request $request, Weathermap $map, ?WeathermapNode $node): array
    {
        $data = $request->validate([
            'network_switch_id' => 'nullable|integer',
            'label' => 'nullable|string|max:60',
            'icon' => ['nullable', Rule::in(array_keys(WeathermapNode::ICONS))],
        ]);

        $device = null;
        if ($data['network_switch_id'] ?? null) {
            $device = NetworkSwitch::visibleTo($request->user())->find($data['network_switch_id'])
                ?? ($node && $node->network_switch_id === (int) $data['network_switch_id'] ? $node->device : null);
            if (! $device) {
                throw \Illuminate\Validation\ValidationException::withMessages(['network_switch_id' => 'Pick a switch or router you can see.']);
            }
        }
        if (! $device && blank($data['label'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['label' => 'Give the node a name, or pick a device.']);
        }

        return [
            'network_switch_id' => $device?->id,
            'label' => filled($data['label'] ?? null) ? trim($data['label']) : $device->name,
            'icon' => $data['icon'] ?? ($device ? ($device->isRouter() ? 'router' : 'switch') : 'cloud'),
        ];
    }

    protected function validatedLink(Request $request, Weathermap $map, ?WeathermapLink $link): array
    {
        $nodeIds = $map->nodes()->pluck('id')->all();
        $data = $request->validate([
            'from_node_id' => ['required', 'integer', Rule::in($nodeIds)],
            'to_node_id' => ['required', 'integer', Rule::in($nodeIds), 'different:from_node_id'],
            'switch_port_id' => 'nullable|integer',
            'bandwidth_mbps' => 'nullable|integer|min:1|max:4000000',
            'label' => 'nullable|string|max:60',
        ], ['to_node_id.different' => 'A link joins two different nodes.']);

        $portId = $data['switch_port_id'] ?? null;
        if ($portId) {
            $visible = NetworkSwitch::visibleTo($request->user())->pluck('id');
            $ok = SwitchPort::whereKey($portId)->where(fn ($q) => $q->whereIn('network_switch_id', $visible)->orWhere('id', $link?->switch_port_id ?? 0))->exists();
            if (! $ok) {
                throw \Illuminate\Validation\ValidationException::withMessages(['switch_port_id' => 'Pick an interface of a switch or router you can see.']);
            }
        }

        return [
            'from_node_id' => (int) $data['from_node_id'],
            'to_node_id' => (int) $data['to_node_id'],
            'switch_port_id' => $portId ? (int) $portId : null,
            'bandwidth_mbps' => $data['bandwidth_mbps'] ?? null,
            'label' => filled($data['label'] ?? null) ? trim($data['label']) : null,
            'reverse' => $request->boolean('reverse'),
        ];
    }

    protected function backToEditor(Weathermap $map, string $message)
    {
        return redirect()->route('maps.show', [$map, 'edit' => 1])->with('success', $message);
    }
}
