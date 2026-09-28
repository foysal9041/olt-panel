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

        BandwidthType::create($validated);

        return back()->with('success', 'Bandwidth Type Added Successfully');
    }

    public function destroy(BandwidthType $bandwidthType)
    {
        if ($bandwidthType->customerRates()->exists()) {
            return back()->with('error', 'This bandwidth type has rates set on existing customers and cannot be removed.');
        }

        $bandwidthType->delete();

        return back()->with('success', 'Bandwidth Type Removed Successfully');
    }
}
