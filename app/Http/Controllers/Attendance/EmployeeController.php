<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\DutyShift;
use App\Models\Employee;
use App\Models\LeaveType;
use Illuminate\Http\Request;

/**
 * The employee roster for attendance tracking — fully separate from the
 * users table (panel logins). An employee is never a login account.
 */
class EmployeeController extends Controller
{
    public function index()
    {
        $authUser = auth()->user();

        $query = Employee::with('dutyShift');

        if (!in_array(strtolower($authUser->role), ['admin', 'noc'])) {
            $query->where('zone', $authUser->zone);
        }

        $employees = $query->orderBy('name')->get();

        return view('attendance.employees.index', compact('employees'));
    }

    public function create()
    {
        return view('attendance.employees.create', [
            'zones' => $this->zones(),
            'shifts' => DutyShift::orderBy('start_time')->get(),
            'leaveTypes' => LeaveType::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $validated['status'] = $request->boolean('status', true);

        $employee = Employee::create($validated);

        $this->savePhoto($employee, $request);
        $this->backfillPunches($employee);
        $this->syncLeaveBalances($employee, $request);

        return redirect()
            ->route('attendance.employees.index')
            ->with('success', 'Employee Added Successfully');
    }

    public function edit(Employee $employee)
    {
        $employee->load('leaveBalances');

        return view('attendance.employees.edit', [
            'employee' => $employee,
            'zones' => $this->zones(),
            'shifts' => DutyShift::orderBy('start_time')->get(),
            'leaveTypes' => LeaveType::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $validated = $this->validated($request, $employee);

        $validated['status'] = $request->boolean('status');

        $employee->update($validated);

        $this->savePhoto($employee, $request);
        $this->backfillPunches($employee);
        $this->syncLeaveBalances($employee, $request);

        return redirect()
            ->route('attendance.employees.index')
            ->with('success', 'Employee Updated Successfully');
    }

    public function destroy(Employee $employee)
    {
        if (strtolower(auth()->user()->role) !== 'admin') {
            abort(403);
        }

        if ($employee->attendanceLogs()->exists()) {
            return back()->with('error', 'This employee has attendance history and cannot be deleted. Mark them inactive instead.');
        }

        $employee->delete();

        return redirect()
            ->route('attendance.employees.index')
            ->with('success', 'Employee Deleted Successfully');
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'designation' => 'nullable|string|max:255',
            'device_user_id' => 'nullable|string|max:255|unique:employees,device_user_id,' . ($employee->id ?? 'NULL'),
            'zone' => 'nullable|exists:zones,name',
            'duty_shift_id' => 'nullable|exists:duty_shifts,id',
            'weekly_off_day' => 'nullable|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'status' => 'nullable|boolean',
            'emp_code' => 'nullable|string|max:30|unique:employees,emp_code,' . ($employee->id ?? 'NULL'),
            'department' => 'nullable|string|max:255',
            'joining_date' => 'nullable|date',
            'blood_group' => 'nullable|in:' . implode(',', Employee::BLOOD_GROUPS),
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'nid' => 'nullable|string|max:30',
        ], ['emp_code.unique' => 'Another employee already has this ID.']);
    }

    /** A photo chosen on the form: square-cropped and kept privately. */
    private function savePhoto(Employee $employee, Request $request): void
    {
        if ($request->hasFile('photo_file')) {
            $request->validate(['photo_file' => 'image|max:8192']);
            app(\App\Services\HrDocs::class)->storePhoto($employee, $request->file('photo_file'));
        }
    }

    /**
     * When an employee gets linked to (or re-linked to) a device PIN, sweep
     * up any punches that already arrived under that PIN before the link
     * existed — otherwise they'd sit unmapped forever.
     */
    private function backfillPunches(Employee $employee): void
    {
        if (!$employee->device_user_id) {
            return;
        }

        AttendanceLog::where('device_user_id', $employee->device_user_id)
            ->whereNull('employee_id')
            ->update(['employee_id' => $employee->id]);
    }

    /**
     * Upserts this employee's per-leave-type day allocation from the
     * "leave_balances[{leave_type_id}]" inputs on the create/edit form.
     * Blank fields are left untouched (existing balance kept).
     */
    private function syncLeaveBalances(Employee $employee, Request $request): void
    {
        $balances = $request->input('leave_balances', []);

        foreach ($balances as $leaveTypeId => $days) {
            if ($days === '' || $days === null) {
                continue;
            }

            $employee->leaveBalances()->updateOrCreate(
                ['leave_type_id' => (int) $leaveTypeId],
                ['days_allocated' => max(0, (int) $days)]
            );
        }
    }

    private function zones()
    {
        return \App\Models\Zone::names();
    }
}
