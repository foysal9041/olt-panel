<?php

namespace App\Http\Controllers;

use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ZoneController extends Controller
{
    public function index()
    {
        $zones = Zone::withCount('olts')->orderBy('name')->get();

        // Switches also reference zones by name.
        $switchCounts = \App\Models\NetworkSwitch::selectRaw('zone, COUNT(*) as c')->whereNotNull('zone')
            ->groupBy('zone')->pluck('c', 'zone');

        return view('zones.index', compact('zones', 'switchCounts'));
    }

    public function create()
    {
        return view('zones.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:zones,name',
        ] + $this->contactRules());

        $validated['own_vlans'] = $request->boolean('own_vlans');

        Zone::create($validated);

        return redirect()
            ->route('zones.index')
            ->with('success', 'Zone Added Successfully');
    }

    public function edit(Zone $zone)
    {
        return view('zones.edit', compact('zone'));
    }

    public function update(Request $request, Zone $zone)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:zones,name,' . $zone->id,
        ] + $this->contactRules());

        $validated['own_vlans'] = $request->boolean('own_vlans');
        $oldName = $zone->name;

        // Renaming also renames it on every OLT, switch, subnet, user … (Zone::booted).
        DB::transaction(fn () => $zone->update($validated));

        return redirect()
            ->route('zones.index')
            ->with('success', $oldName !== $zone->name
                ? "Zone renamed to “{$zone->name}” — updated everywhere it's used."
                : 'Zone Updated Successfully');
    }

    public function destroy(Zone $zone)
    {
        if ($used = $zone->references()) {
            $list = collect($used)->map(fn ($n, $what) => "{$n} {$what}")->implode(', ');

            return back()->with('error', "“{$zone->name}” is still used by {$list} — move them to another zone first.");
        }

        $zone->delete();

        return redirect()
            ->route('zones.index')
            ->with('success', 'Zone Deleted Successfully');
    }

    protected function contactRules(): array
    {
        return [
            'code' => 'nullable|string|max:50',
            'username' => 'nullable|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
