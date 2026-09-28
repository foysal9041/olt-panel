<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksVlanOverlap;
use App\Models\Vlan;
use App\Models\Zone;
use Illuminate\Http\Request;

class VlanController extends Controller
{
    use ChecksVlanOverlap;

    public function index()
    {
        $vlans = Vlan::orderBy('vlan')->get();

        return view('vlans.index', compact('vlans'));
    }

    public function create()
    {
        $zones = Zone::orderBy('name')->pluck('name');

        return view('vlans.create', compact('zones'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vlan'    => 'required|string|max:20|unique:vlans,vlan',
            'name'    => 'required|string|max:255',
            'zone'    => 'nullable|string',
            'status'  => 'required|in:active,reserved',
            'remarks' => 'nullable|string',
        ]);

        $this->assertVlanNotOverlapping($validated['vlan']);

        Vlan::create($validated);

        return redirect()
            ->route('vlans.index')
            ->with('success', 'VLAN Added Successfully');
    }

    public function edit(Vlan $vlan)
    {
        $zones = Zone::orderBy('name')->pluck('name');

        return view('vlans.edit', compact('vlan', 'zones'));
    }

    public function update(Request $request, Vlan $vlan)
    {
        $validated = $request->validate([
            'vlan'    => 'required|string|max:20|unique:vlans,vlan,' . $vlan->id,
            'name'    => 'required|string|max:255',
            'zone'    => 'nullable|string',
            'status'  => 'required|in:active,reserved',
            'remarks' => 'nullable|string',
        ]);

        $this->assertVlanNotOverlapping($validated['vlan'], null, $vlan->id);

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
}
