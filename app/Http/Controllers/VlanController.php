<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksVlanOverlap;
use App\Models\Vlan;
use App\Models\Zone;
use App\Services\VlanInventory;
use App\Support\VlanRange;
use Illuminate\Http\Request;

class VlanController extends Controller
{
    use ChecksVlanOverlap;

    public function index(Request $request)
    {
        $vlans = Vlan::orderByRaw('CAST(vlan AS UNSIGNED)')->get();
        $inventory = new VlanInventory;
        $zones = Zone::orderBy('name')->pluck('name');

        // Which VLAN space: the core network, or a POP with its own VLANs.
        $net = $inventory->networkFor($request->query('net'));

        // Find free VLANs: how many, within which range, starting on a multiple of.
        $align = (int) $request->query('align', 1);
        $find = [
            'net' => $net,
            'count' => max(1, min(500, (int) $request->query('count', 4))),
            'from' => max(VlanInventory::MIN, min(VlanInventory::MAX, (int) $request->query('from', 2))),
            'to' => max(VlanInventory::MIN, min(VlanInventory::MAX, (int) $request->query('to', VlanInventory::MAX))),
            'align' => in_array($align, [1, 5, 10], true) ? $align : 1,
        ];
        if ($find['from'] > $find['to']) {
            [$find['from'], $find['to']] = [$find['to'], $find['from']];
        }
        $result = $request->has('count')
            ? $inventory->findFree($find['count'], $find['from'], $find['to'], $find['align'], network: $net)
            : null;

        // Check one VLAN / range: who uses it?
        $check = trim((string) $request->query('check', ''));
        $checkResult = $check !== '' && VlanRange::parseList($check)
            ? $inventory->conflicts($check, network: $net)
            : null;

        return view('vlans.index', [
            'vlans' => $vlans,
            'zones' => $zones,
            'net' => $net,
            'ownZones' => array_values($inventory->ownZones()),
            'find' => $find,
            'result' => $result,
            'check' => $check,
            'checkResult' => $checkResult,
            'duplicates' => $inventory->duplicates(),
            'entries' => $inventory->entries(),
            'usedCount' => count($inventory->usage($net)),
            'series' => $inventory->series($net),
            'takenBy' => $inventory->takenReservations(),
        ]);
    }

    public function create()
    {
        $zones = Zone::orderBy('name')->pluck('name');

        return view('vlans.create', compact('zones'));
    }

    public function store(Request $request)
    {
        $this->normalizeVlan($request);

        $validated = $request->validate([
            'vlan'    => ['required', 'string', 'max:20', 'regex:/^\s*\d{1,4}\s*(-\s*\d{1,4}\s*)?$/', 'unique:vlans,vlan'],
            'name'    => 'required|string|max:255',
            'zone'    => 'nullable|exists:zones,name',
            'status'  => 'required|in:active,reserved',
            'remarks' => 'nullable|string',
        ], $this->messages());

        $this->assertVlanNotOverlapping($validated['vlan'], reservedIsFree: false, zone: $validated['zone'] ?? null);

        Vlan::create($validated);

        return redirect()
            ->route('vlans.index')
            ->with('success', $validated['status'] === 'reserved'
                ? "VLAN {$validated['vlan']} reserved for {$validated['name']}."
                : 'VLAN Added Successfully');
    }

    public function edit(Vlan $vlan)
    {
        $zones = Zone::orderBy('name')->pluck('name');

        return view('vlans.edit', compact('vlan', 'zones'));
    }

    public function update(Request $request, Vlan $vlan)
    {
        $this->normalizeVlan($request);

        $validated = $request->validate([
            'vlan'    => ['required', 'string', 'max:20', 'regex:/^\s*\d{1,4}\s*(-\s*\d{1,4}\s*)?$/', 'unique:vlans,vlan,' . $vlan->id],
            'name'    => 'required|string|max:255',
            'zone'    => 'nullable|exists:zones,name',
            'status'  => 'required|in:active,reserved',
            'remarks' => 'nullable|string',
        ], $this->messages());

        $this->assertVlanNotOverlapping($validated['vlan'], excludeVlanId: $vlan->id, reservedIsFree: false, zone: $validated['zone'] ?? null);

        $vlan->update($validated);

        return redirect()
            ->route('vlans.index')
            ->with('success', 'VLAN Updated Successfully');
    }

    public function destroy(Vlan $vlan)
    {
        $vlan->delete();

        return redirect()
            ->route('vlans.index')
            ->with('success', 'VLAN Deleted Successfully');
    }

    /**
     * "2505 - 2508" -> "2505-2508"; a reversed range is put right.
     */
    private function normalizeVlan(Request $request): void
    {
        $vlan = preg_replace('/\s+/', '', (string) $request->input('vlan'));

        if (preg_match('/^(\d+)-(\d+)$/', $vlan, $m) && (int) $m[1] > (int) $m[2]) {
            $vlan = "{$m[2]}-{$m[1]}";
        }

        if (preg_match('/^(\d+)-\1$/', $vlan, $m)) {
            $vlan = $m[1];
        }

        $request->merge(['vlan' => $vlan]);
    }

    private function messages(): array
    {
        return [
            'vlan.regex' => 'Enter one VLAN (e.g. 20) or a range (e.g. 2505-2508).',
            'vlan.unique' => 'This VLAN is already in VLAN Management.',
        ];
    }
}
