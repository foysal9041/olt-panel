<?php

namespace App\Http\Controllers;

use App\Models\Olt;
use App\Models\Zone;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    public function index()
    {
        $zones = Zone::withCount('olts')->orderBy('name')->get();

        return view('zones.index', compact('zones'));
    }

    public function create()
    {
        return view('zones.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:zones,name',
        ]);

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
        ]);

        $zone->update($validated);

        return redirect()
            ->route('zones.index')
            ->with('success', 'Zone Updated Successfully');
    }

    public function destroy(Zone $zone)
    {
        if (Olt::where('zone', $zone->name)->exists()) {
            return back()->with('error', 'This zone has OLTs assigned to it and cannot be deleted.');
        }

        $zone->delete();

        return redirect()
            ->route('zones.index')
            ->with('success', 'Zone Deleted Successfully');
    }
}
