<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksSubnetOverlap;
use App\Models\IpPool;
use App\Models\Zone;
use Illuminate\Http\Request;

class IpPoolController extends Controller
{
    use ChecksSubnetOverlap;

    public function index()
    {
        $ipPools = IpPool::orderBy('subnet')->get();

        return view('ip_pools.index', compact('ipPools'));
    }

    public function create()
    {
        $zones = Zone::orderBy('name')->pluck('name');

        return view('ip_pools.create', compact('zones'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subnet'      => 'required|string|max:50|unique:ip_pools,subnet',
            'gateway'     => 'nullable|string|max:50',
            'type'        => 'required|in:public,private',
            'zone'        => 'nullable|string',
            'vlan'        => 'nullable|string|max:20',
            'status'      => 'required|in:active,inactive',
            'description' => 'nullable|string',
        ]);

        $this->assertSubnetNotOverlapping($validated['subnet']);

        IpPool::create($validated);

        return redirect()
            ->route('ip-pools.index')
            ->with('success', 'IP Subnet Added Successfully');
    }

    public function edit(IpPool $ipPool)
    {
        $zones = Zone::orderBy('name')->pluck('name');

        return view('ip_pools.edit', compact('ipPool', 'zones'));
    }

    public function update(Request $request, IpPool $ipPool)
    {
        $validated = $request->validate([
            'subnet'      => 'required|string|max:50|unique:ip_pools,subnet,' . $ipPool->id,
            'gateway'     => 'nullable|string|max:50',
            'type'        => 'required|in:public,private',
            'zone'        => 'nullable|string',
            'vlan'        => 'nullable|string|max:20',
            'status'      => 'required|in:active,inactive',
            'description' => 'nullable|string',
        ]);

        $this->assertSubnetNotOverlapping($validated['subnet'], $ipPool->id);

        $ipPool->update($validated);

        return redirect()
            ->route('ip-pools.index')
            ->with('success', 'IP Subnet Updated Successfully');
    }

    public function destroy(IpPool $ipPool)
    {
        $ipPool->delete();

        return redirect()
            ->route('ip-pools.index')
            ->with('success', 'IP Subnet Deleted Successfully');
    }
}
