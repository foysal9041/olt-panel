<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\BandwidthType;
use Illuminate\Http\Request;

class BandwidthTypeController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:bandwidth_types,name',
        ]);

        BandwidthType::create($validated + ['flat' => $request->boolean('flat'), 'sort' => (int) BandwidthType::max('sort') + 1]);

        return back()->with('success', 'Bandwidth Type Added Successfully');
    }

    public function destroy(BandwidthType $bandwidthType)
    {
        if ($bandwidthType->customerRates()->exists() || $bandwidthType->serviceChanges()->exists()) {
            return back()->with('error', 'This bandwidth type has rates set on existing customers and cannot be removed.');
        }

        $bandwidthType->delete();

        return back()->with('success', 'Bandwidth Type Removed Successfully');
    }

    /** Fixed monthly amount (VAS, Billing) or billed per Mbps by days. */
    public function update(Request $request, BandwidthType $bandwidthType)
    {
        $bandwidthType->update(['flat' => $request->boolean('flat')]);

        return back()->with('success', "{$bandwidthType->name} is now " . ($bandwidthType->flat ? 'a fixed monthly amount.' : 'billed per Mbps.'));
    }
}
