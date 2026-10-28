<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\AttendanceSetting;
use App\Models\DutyShift;
use App\Models\Leave;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Single source of truth for turning raw attendance_logs punches into a
 * present / late / absent status for a given employee and day. Used by the
 * attendance dashboard, attendance report and absence report so the three
 * pages never disagree with each other.
 *
 * "Late" is judged against each employee's own duty shift (App\Models\DutyShift)
 * and their own weekly off day (App\Models\Employee::effectiveWeeklyOffDay) —
 * both fall back to a company-wide default (DutyShift::default(),
 * AttendanceSetting) when an employee doesn't have their own set. A day with
 * no punches but an approved Leave covering it is "leave", not "absent".
 *
 * Only punches inside the office time (AttendanceSetting::workDay) count.
 *
 * Late minutes run from the shift's start to the check-in (only on a late
 * day — past the grace period). Extra duty is the time from the shift's end
 * to the check-out (needs the shift's end time), and on the weekly off day
 * the whole time in the office.
 */
class AttendanceCalculator
{
    public function isWorkingDay(Carbon $date, string $weeklyOffDay): bool
    {
        return $date->format('l') !== $weeklyOffDay;
    }

    /**
     * A punch this soon after check-in is the same finger pressed twice,
     * not a check-out.
     */
    public const DOUBLE_TAP_SECONDS = 120;

    /**
     * The day's first punch is the check-in and its last punch the check-out
     * (whatever In / Out key was pressed on the device); a last punch within
     * DOUBLE_TAP_SECONDS of the check-in isn't a check-out.
     *
     * @return array{0: ?\App\Models\AttendanceLog, 1: ?\App\Models\AttendanceLog}
     */
    public static function inAndOut(Collection $dayLogs): array
    {
        $sorted = $dayLogs->sortBy('punched_at')->values();
        $in = $sorted->first();
        $out = $sorted->count() > 1 ? $sorted->last() : null;
        if ($in && $out && $in->punched_at->diffInSeconds($out->punched_at, true) < self::DOUBLE_TAP_SECONDS) {
            $out = null;
        }

        return [$in, $out];
    }

    /** Whole minutes from $from to $to; 0 when $to isn't after $from. */
    public static function minutesBetween(Carbon $from, Carbon $to): int
    {
        return max(0, intdiv($to->getTimestamp() - $from->getTimestamp(), 60));
    }

    /**
     * When the shift ends on $date; an end at or before the start is the
     * next morning. Null when the shift has no end time.
     */
    public static function shiftEnd(Carbon $date, DutyShift $shift): ?Carbon
    {
        if (! $shift->end_time) {
            return null;
        }
        $end = $date->copy()->setTimeFromTimeString($shift->end_time);

        return $end->lte($date->copy()->setTimeFromTimeString($shift->start_time)) ? $end->addDay() : $end;
    }

    /**
     * Status for one employee on one day, given the logs already narrowed
     * down to that employee/day (avoids re-querying per cell in a report).
     *
     * @return array{status: string, check_in: ?Carbon, check_out: ?Carbon, hours_worked: ?float, overtime_hours: ?float, late_minutes: ?int, extra_minutes: ?int}
     */
    public function dayStatus(Carbon $date, string $weeklyOffDay, DutyShift $shift, Collection $dayLogs, bool $onApprovedLeave = false): array
    {
        // Came in on the weekly off day: recorded, and all of it is overtime.
        if (!$this->isWorkingDay($date, $weeklyOffDay)) {
            if ($dayLogs->isEmpty()) {
                return self::noPunches('weekend');
            }
            [$in, $out] = self::inAndOut($dayLogs);
            $hours = $out ? round($in->punched_at->floatDiffInRealHours($out->punched_at), 2) : null;

            return [
                'status' => 'overtime',
                'check_in' => $in->punched_at,
                'check_out' => $out?->punched_at,
                'hours_worked' => $hours,
                'overtime_hours' => $hours,
                'late_minutes' => null,
                'extra_minutes' => $out ? self::minutesBetween($in->punched_at, $out->punched_at) : null,
            ];
        }

        if ($dayLogs->isEmpty()) {
            return self::noPunches($onApprovedLeave ? 'leave' : ($date->isFuture() ? 'upcoming' : 'absent'));
        }

        [$in, $out] = self::inAndOut($dayLogs);
        $checkIn = $in->punched_at;
        $checkOut = $out?->punched_at;

        $start = $date->copy()->setTimeFromTimeString($shift->start_time);
        $late = $checkIn->gt($start->copy()->addMinutes($shift->late_grace_minutes));
        $end = self::shiftEnd($date, $shift);

        return [
            'status' => $late ? 'late' : 'present',
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'hours_worked' => $checkOut ? round($checkIn->floatDiffInRealHours($checkOut), 2) : null,
            'overtime_hours' => null,
            // Counted from the shift start, not the end of the grace period.
            'late_minutes' => $late ? self::minutesBetween($start, $checkIn) : null,
            'extra_minutes' => $checkOut && $end ? self::minutesBetween($end, $checkOut) : null,
        ];
    }

    protected static function noPunches(string $status): array
    {
        return ['status' => $status, 'check_in' => null, 'check_out' => null, 'hours_worked' => null, 'overtime_hours' => null, 'late_minutes' => null, 'extra_minutes' => null];
    }

    /**
     * Per-employee, per-day status grid for the given date range.
     *
     * @return array<int, array<string, array{status: string, check_in: ?Carbon, check_out: ?Carbon, hours_worked: ?float, overtime_hours: ?float, late_minutes: ?int, extra_minutes: ?int}>>
     */
    public function buildReport(Collection $employees, Carbon $start, Carbon $end, AttendanceSetting $settings): array
    {
        // Only punches inside office time count, each for its attendance day
        // (an after-midnight punch can belong to the day before).
        $logsByEmployeeAndDay = AttendanceLog::whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('punched_at', $settings->workDayRange($start, $end))
            ->orderBy('punched_at')
            ->get()
            ->map(fn (AttendanceLog $log) => [$log, $settings->workDay($log->punched_at)])
            ->filter(fn ($pair) => $pair[1] !== null)
            ->groupBy(fn ($pair) => $pair[0]->employee_id . '|' . $pair[1])
            ->map(fn (Collection $pairs) => $pairs->pluck(0));

        $leavesByEmployee = Leave::approved()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->overlapping($start->toDateString(), $end->toDateString())
            ->get()
            ->groupBy('employee_id');

        $defaultShift = null;
        $report = [];

        foreach ($employees as $employee) {
            $shift = $employee->dutyShift ?? ($defaultShift ??= DutyShift::default());
            $weeklyOffDay = $employee->weekly_off_day ?? $settings->weekly_off_day;
            $employeeLeaves = $leavesByEmployee->get($employee->id, collect());
            $days = [];

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $key = $employee->id . '|' . $date->toDateString();
                $onLeave = $employeeLeaves->contains(
                    fn (Leave $leave) => $date->betweenIncluded($leave->start_date, $leave->end_date)
                );

                $days[$date->toDateString()] = $this->dayStatus(
                    $date->copy(),
                    $weeklyOffDay,
                    $shift,
                    $logsByEmployeeAndDay->get($key, collect()),
                    $onLeave
                );
            }

            $report[$employee->id] = $days;
        }

        return $report;
    }

    /**
     * Per employee over a report: days present / late / absent / on leave,
     * overtime days and hours (weekly off days worked), hours worked, total
     * late minutes, and extra-duty minutes — after the shift on working days
     * (extra_shift) plus whole weekly off days (extra_offday).
     *
     * @return array<int, array{present: int, late: int, absent: int, leave: int, overtime_days: int, overtime_hours: float, hours: float, late_minutes: int, extra_shift: int, extra_offday: int, extra_minutes: int}>
     */
    public function totals(array $report): array
    {
        $totals = [];
        foreach ($report as $employeeId => $days) {
            $t = ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'overtime_days' => 0, 'overtime_hours' => 0.0, 'hours' => 0.0, 'late_minutes' => 0, 'extra_shift' => 0, 'extra_offday' => 0];
            foreach ($days as $info) {
                match ($info['status']) {
                    'present', 'late', 'absent', 'leave' => $t[$info['status']]++,
                    'overtime' => $t['overtime_days']++,
                    default => null,
                };
                $t['overtime_hours'] += (float) ($info['overtime_hours'] ?? 0);
                $t['hours'] += (float) ($info['hours_worked'] ?? 0);
                $t['late_minutes'] += (int) ($info['late_minutes'] ?? 0);
                $t[$info['status'] === 'overtime' ? 'extra_offday' : 'extra_shift'] += (int) ($info['extra_minutes'] ?? 0);
            }
            $t['extra_minutes'] = $t['extra_shift'] + $t['extra_offday'];
            $t['overtime_hours'] = round($t['overtime_hours'], 2);
            $t['hours'] = round($t['hours'], 2);
            $totals[$employeeId] = $t;
        }

        return $totals;
    }

    /**
     * Today's present / late / absent counts for the dashboard info-boxes.
     *
     * @return array{present: int, late: int, absent: int, total: int}
     */
    public function todaySummary(Collection $employees, AttendanceSetting $settings): array
    {
        $today = Carbon::today();
        $report = $this->buildReport($employees, $today, $today, $settings);

        $counts = ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'weekend' => 0, 'overtime' => 0, 'total' => $employees->count()];

        foreach ($report as $days) {
            $status = $days[$today->toDateString()]['status'];

            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }

        return $counts;
    }

    /**
     * Day-by-day counts for the last N days, for the trend chart: present
     * (on time + late), absent, overtime, on leave, and the attendance rate
     * of those expected in (not on leave or their weekly off). Pass the
     * report for those days when it's already built.
     *
     * @return array<int, array{date: string, present: int, on_time: int, late: int, absent: int, leave: int, overtime: int, expected: int, rate: ?int}>
     */
    public function trend(Collection $employees, AttendanceSetting $settings, int $days = 7, ?array $report = null): array
    {
        $end = Carbon::today();
        $start = $end->copy()->subDays($days - 1);

        $report ??= $this->buildReport($employees, $start, $end, $settings);

        $trend = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();
            $n = ['on_time' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'overtime' => 0];

            foreach ($report as $employeeDays) {
                $status = $employeeDays[$key]['status'] ?? null;
                $status = $status === 'present' ? 'on_time' : $status;

                if (isset($n[$status])) {
                    $n[$status]++;
                }
            }

            $present = $n['on_time'] + $n['late'];
            $expected = $present + $n['absent'];
            $trend[] = ['date' => $key, 'present' => $present] + $n + ['expected' => $expected, 'rate' => $expected ? (int) round($present / $expected * 100) : null];
        }

        return $trend;
    }
}
