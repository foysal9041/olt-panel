<?php

namespace App\Http\Controllers;

use App\Models\Leave;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Apply for leave yourself: the request goes to HR as "pending" and shows
 * up in Leave Management to approve or reject. Needs the login to be linked
 * to an employee (Users → edit → Employee record).
 */
class MyLeaveController extends Controller
{
    public function index(Request $request)
    {
        $employee = $request->user()->employee?->load('leaveBalances');
        $types = LeaveType::orderBy('name')->get();

        $balances = $employee ? $types->map(function (LeaveType $type) use ($employee) {
            $limited = $type->default_days_per_year !== null || $employee->leaveBalances->firstWhere('leave_type_id', $type->id);
            $pending = $employee->leaves()->where('status', 'pending')->where('leave_type_id', $type->id)->whereYear('start_date', now()->year)
                ->get()->sum(fn (Leave $l) => $l->daysCount());

            return [
                'type' => $type,
                'limited' => (bool) $limited,
                'allocated' => $employee->allocatedLeaveDays($type),
                'used' => $employee->usedLeaveDays($type),
                'pending' => $pending,
                'left' => $employee->remainingLeaveDays($type),
            ];
        }) : collect();

        return view('my.leave', [
            'employee' => $employee,
            'types' => $types,
            'balances' => $balances,
            'leaves' => $employee ? $employee->leaves()->with(['leaveType', 'approvedBy'])->latest('start_date')->limit(50)->get() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return back()->with('error', 'Your login is not linked to an employee record yet — ask an admin to link it (Users → edit).');
        }

        $data = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date|after_or_equal:' . now()->subDays(30)->toDateString(),
            'end_date' => 'required|date|after_or_equal:start_date|before_or_equal:' . now()->addYear()->toDateString(),
            'reason' => 'required|string|max:1000',
        ], [
            'start_date.after_or_equal' => 'Leave more than 30 days back has to be entered by HR.',
        ]);

        $days = Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date'])) + 1;
        if ($days > 60) {
            return back()->withInput()->with('error', 'That is more than 60 days — talk to HR for a long leave.');
        }

        $overlap = $employee->leaves()->whereIn('status', ['pending', 'approved'])->overlapping($data['start_date'], $data['end_date'])->first();
        if ($overlap) {
            return back()->withInput()->with('error', "You already have {$overlap->status} leave from {$overlap->start_date->format('d M')} to {$overlap->end_date->format('d M Y')}.");
        }

        Leave::create($data + [
            'employee_id' => $employee->id,
            'status' => 'pending',
            'applied_by' => $request->user()->id,
        ]);

        return back()->with('success', "Leave request sent ({$days} " . ($days == 1 ? 'day' : 'days') . ') — HR will approve it.');
    }

    /** Take back a request that hasn't been decided yet. */
    public function destroy(Request $request, Leave $leave)
    {
        abort_unless($leave->employee_id === $request->user()->employee_id && $leave->status === 'pending', 403);
        $leave->delete();

        return back()->with('success', 'Leave request cancelled.');
    }
}
