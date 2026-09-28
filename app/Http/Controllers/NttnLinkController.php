<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksSubnetOverlap;
use App\Http\Controllers\Concerns\ChecksVlanOverlap;
use App\Models\NttnLink;
use App\Models\Zone;
use Illuminate\Http\Request;

class NttnLinkController extends Controller
{
    use ChecksVlanOverlap;
    use ChecksSubnetOverlap;

    public function index()
    {
        $nttnLinks = NttnLink::orderBy('link_id')->get();

        return view('nttn_links.index', compact('nttnLinks'));
    }

    public function create()
    {
        $zones = Zone::orderBy('name')->pluck('name');

        return view('nttn_links.create', compact('zones'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'link_id'            => 'required|string|max:100|unique:nttn_links,link_id',
            'provider'           => 'nullable|string|max:255',
            'address'            => 'required|string',
            'bandwidth'          => 'required|string|max:50',
            'public_ip_subnet'   => 'nullable|string|max:100',
            'private_ip_subnet'  => 'nullable|string|max:100',
            'peering_ip'         => 'nullable|string|max:100|unique:nttn_links,peering_ip',
            'peering_vlan'       => 'nullable|string|max:20',
            'asn'                => 'nullable|string|max:20',
            'location'           => 'required|string|max:255',
            'zone'               => 'nullable|string',
            'status'             => 'required|in:active,inactive',
            'remarks'            => 'nullable|string',
        ]);

        $this->assertVlanNotOverlapping(
            $validated['peering_vlan'] ?? '',
            fieldName: 'peering_vlan'
        );

        $this->assertSubnetNotOverlapping(
            $validated['public_ip_subnet'] ?? '',
            fieldName: 'public_ip_subnet'
        );

        $this->assertSubnetNotOverlapping(
            $validated['private_ip_subnet'] ?? '',
            fieldName: 'private_ip_subnet'
        );

        NttnLink::create($validated);

        return redirect()
            ->route('nttn-links.index')
            ->with('success', 'NTTN Link Added Successfully');
    }

    public function show(NttnLink $nttnLink)
    {
        return view('nttn_links.show', compact('nttnLink'));
    }

    public function edit(NttnLink $nttnLink)
    {
        $zones = Zone::orderBy('name')->pluck('name');

        return view('nttn_links.edit', compact('nttnLink', 'zones'));
    }

    public function update(Request $request, NttnLink $nttnLink)
    {
        $validated = $request->validate([
            'link_id'            => 'required|string|max:100|unique:nttn_links,link_id,' . $nttnLink->id,
            'provider'           => 'nullable|string|max:255',
            'address'            => 'required|string',
            'bandwidth'          => 'required|string|max:50',
            'public_ip_subnet'   => 'nullable|string|max:100',
            'private_ip_subnet'  => 'nullable|string|max:100',
            'peering_ip'         => 'nullable|string|max:100|unique:nttn_links,peering_ip,' . $nttnLink->id,
            'peering_vlan'       => 'nullable|string|max:20',
            'asn'                => 'nullable|string|max:20',
            'location'           => 'required|string|max:255',
            'zone'               => 'nullable|string',
            'status'             => 'required|in:active,inactive',
            'remarks'            => 'nullable|string',
        ]);

        $this->assertVlanNotOverlapping(
            $validated['peering_vlan'] ?? '',
            excludeNttnLinkId: $nttnLink->id,
            fieldName: 'peering_vlan'
        );

        $this->assertSubnetNotOverlapping(
            $validated['public_ip_subnet'] ?? '',
            excludeNttnLinkId: $nttnLink->id,
            fieldName: 'public_ip_subnet'
        );

        $this->assertSubnetNotOverlapping(
            $validated['private_ip_subnet'] ?? '',
            excludeNttnLinkId: $nttnLink->id,
            fieldName: 'private_ip_subnet'
        );

        $nttnLink->update($validated);

        return redirect()
            ->route('nttn-links.index')
            ->with('success', 'NTTN Link Updated Successfully');
    }

    public function destroy(NttnLink $nttnLink)
    {
        $nttnLink->delete();

        return redirect()
            ->route('nttn-links.index')
            ->with('success', 'NTTN Link Deleted Successfully');
    }
}
