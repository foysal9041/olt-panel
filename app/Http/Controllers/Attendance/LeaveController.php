<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveType;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $query = Leave::with(['employee', 'leaveType', 'approvedBy']);

        if (!in_array(strtolower(auth()->user()->role), ['admin', 'noc'])) {
            $query->whereHas('employee', fn ($q) => $q->where('zone', auth()->user()->zone));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        $leaves = $query->orderByDesc('start_date')->get();

        return view('attendance.leaves.index', [
            'leaves' => $leaves,
            'employees' => $this->employees(),
            'leaveTypes' => LeaveType::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('attendance.leaves.create', [
            'employees' => $this->employees(),
            'leaveTypes' => LeaveType::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $validated['applied_by'] = auth()->id();

        if ($validated['status'] === 'approved') {
            $validated['approved_by'] = auth()->id();
            $validated['approved_at'] = now();
        }

        Leave::create($validated);

        return redirect()
            ->route('attendance.leaves.index')
            ->with('success', 'Leave Recorded Successfully');
    }

    public function edit(Leave $leave)
    {
        return view('attendance.leaves.edit', [
            'leave' => $leave,
            'employees' => $this->employees(),
            'leaveTypes' => LeaveType::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Leave $leave)
    {
        $validated = $this->validated($request);

        if ($validated['status'] === 'approved' && !$leave->isApproved()) {
            $validated['approved_by'] = auth()->id();
            $validated['approved_at'] = now();
        }

        $leave->update($validated);

        return redirect()
            ->route('attendance.leaves.index')
            ->with('success', 'Leave Updated Successfully');
    }

    public function approve(Leave $leave)
    {
        $leave->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', "Leave for {$leave->employee->name} approved.");
    }

    public function reject(Leave $leave)
    {
        $leave->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', "Leave for {$leave->employee->name} rejected.");
    }

    public function destroy(Leave $leave)
    {
        if (strtolower(auth()->user()->role) !== 'admin') {
            abort(403);
        }

        $leave->delete();

        return redirect()
            ->route('attendance.leaves.index')
            ->with('success', 'Leave Deleted Successfully');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:1000',
            'status' => 'required|in:pending,approved,rejected',
        ]);
    }

    private function employees()
    {
        $query = Employee::query();

        if (!in_array(strtolower(auth()->user()->role), ['admin', 'noc'])) {
            $query->where('zone', auth()->user()->zone);
        }

        return $query->orderBy('name')->get();
    }
}
