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
        'photo',
        'phone',
        'email',
        'address',
        'nid',
        'designation',
        'department',
        'joining_date',
        'blood_group',
        'left_on',
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
            'joining_date' => 'date',
            'left_on' => 'date',
        ];
    }

    public const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    public const DEPARTMENTS = ['IT & Network', 'NOC', 'Support', 'Field Operations', 'Accounts', 'Administration', 'Management', 'Sales & Marketing'];

    /** ID shown on the card and letters: the code if set, else SNDC-0xx. */
    public function empId(): string
    {
        return $this->emp_code ?: 'SNDC-' . str_pad((string) $this->id, 3, '0', STR_PAD_LEFT);
    }

    /** The photo for signed-in pages (null without one). */
    public function photoUrl(): ?string
    {
        return $this->photo ? route('attendance.employees.photo', [$this, 'v' => substr(md5($this->photo), 0, 8)]) : null;
    }

    /** Public link printed as the ID card's QR: shows whether the card is valid. */
    public function verifyUrl(): string
    {
        // Signed without the host, so it checks out however the site is reached.
        return rtrim(config('app.url'), '/') . \Illuminate\Support\Facades\URL::signedRoute('staff.verify', ['employee' => $this->id], absolute: false);
    }

    public function hasLeft(): bool
    {
        return ! $this->status || ($this->left_on && $this->left_on->lt(today()));
    }

    public function idCards()
    {
        return $this->hasMany(IdCard::class);
    }

    /** The login that belongs to this employee, if any. */
    public function user()
    {
        return $this->hasOne(User::class);
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
