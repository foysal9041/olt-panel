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
        $nttnLink = new NttnLink(['status' => 'active', 'monitor' => true]);
        $zones = Zone::orderBy('name')->pluck('name');
        $providers = $this->providerSuggestions();

        return view('nttn_links.create', compact('nttnLink', 'zones', 'providers'));
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
            'ping_ip'            => 'nullable|ip',
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

        // Every link is pinged by the monitor; there's no opt-out.
        $validated['monitor'] = true;

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
        $providers = $this->providerSuggestions();

        return view('nttn_links.edit', compact('nttnLink', 'zones', 'providers'));
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
            'ping_ip'            => 'nullable|ip',
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

        $validated['monitor'] = true;

        // Changing where we ping starts the up/down state over.
        if (($validated['ping_ip'] ?? null) !== $nttnLink->ping_ip || ($validated['peering_ip'] ?? null) !== $nttnLink->peering_ip) {
            $nttnLink->forceFill(['link_state' => null, 'state_streak' => 0, 'state_changed_at' => null]);
        }

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

    /**
     * Providers already used, plus the common Bangladeshi NTTN operators.
     */
    protected function providerSuggestions()
    {
        return NttnLink::whereNotNull('provider')->distinct()->pluck('provider')
            ->merge(['Fiber@Home', 'Summit Communications', 'BTCL', 'Bahon', 'PGCB'])
            ->unique()
            ->sort()
            ->values();
    }
}
