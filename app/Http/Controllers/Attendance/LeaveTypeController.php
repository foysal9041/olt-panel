<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use Illuminate\Http\Request;

class LeaveTypeController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:leave_types,name',
            'default_days_per_year' => 'nullable|integer|min:0|max:365',
        ]);

        LeaveType::create($validated);

        return back()->with('success', 'Leave Type Added Successfully');
    }

    public function destroy(LeaveType $leaveType)
    {
        if ($leaveType->leaves()->exists()) {
            return back()->with('error', 'This leave type has leave records and cannot be deleted.');
        }

        $leaveType->delete();

        return back()->with('success', 'Leave Type Removed Successfully');
    }
}
