<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use LogsActivity;

    protected $fillable = [
        'emp_code',
        'name',
        'phone',
        'designation',
        'basic_salary',
        'house_rent',
        'device_user_id',
        'zone',
        'duty_shift_id',
        'weekly_off_day',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    public function dutyShift()
    {
        return $this->belongsTo(DutyShift::class);
    }

    public function effectiveDutyShift(): DutyShift
    {
        return $this->dutyShift ?? DutyShift::default();
    }

    /**
     * This employee's weekly off day, or the company-wide default from
     * AttendanceSetting if they don't have one of their own set.
     */
    public function effectiveWeeklyOffDay(): string
    {
        return $this->weekly_off_day ?? AttendanceSetting::current()->weekly_off_day;
    }

    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class);
    }

    public function leaveBalances()
    {
        return $this->hasMany(EmployeeLeaveBalance::class);
    }

    /**
     * Days allocated to this employee for a leave type — their own override
     * if set, otherwise that type's company-wide default (0 if neither).
     */
    public function allocatedLeaveDays(LeaveType $type): int
    {
        $override = $this->leaveBalances->firstWhere('leave_type_id', $type->id);

        return $override->days_allocated ?? $type->default_days_per_year ?? 0;
    }

    /**
     * Approved leave days of this type taken in the given year (current
     * year if omitted), counted by each leave's start date.
     */
    public function usedLeaveDays(LeaveType $type, ?int $year = null): int
    {
        $year ??= now()->year;

        return $this->leaves()
            ->approved()
            ->where('leave_type_id', $type->id)
            ->whereYear('start_date', $year)
            ->get()
            ->sum(fn (Leave $leave) => $leave->daysCount());
    }

    public function remainingLeaveDays(LeaveType $type, ?int $year = null): int
    {
        return $this->allocatedLeaveDays($type) - $this->usedLeaveDays($type, $year);
    }
}
