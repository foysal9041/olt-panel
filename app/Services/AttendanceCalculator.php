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
 */
class AttendanceCalculator
{
    public function isWorkingDay(Carbon $date, string $weeklyOffDay): bool
    {
        return $date->format('l') !== $weeklyOffDay;
    }

    /**
     * Status for one employee on one day, given the logs already narrowed
     * down to that employee/day (avoids re-querying per cell in a report).
     *
     * @return array{status: string, check_in: ?Carbon, check_out: ?Carbon, hours_worked: ?float}
     */
    public function dayStatus(Carbon $date, string $weeklyOffDay, DutyShift $shift, Collection $dayLogs, bool $onApprovedLeave = false): array
    {
        if (!$this->isWorkingDay($date, $weeklyOffDay)) {
            return ['status' => 'weekend', 'check_in' => null, 'check_out' => null, 'hours_worked' => null];
        }

        if ($dayLogs->isEmpty()) {
            if ($onApprovedLeave) {
                return ['status' => 'leave', 'check_in' => null, 'check_out' => null, 'hours_worked' => null];
            }

            return [
                'status' => $date->isFuture() ? 'upcoming' : 'absent',
                'check_in' => null,
                'check_out' => null,
                'hours_worked' => null,
            ];
        }

        $checkIn = $dayLogs->first()->punched_at;
        $checkOut = $dayLogs->count() > 1 ? $dayLogs->last()->punched_at : null;

        $cutoff = $date->copy()
            ->setTimeFromTimeString($shift->start_time)
            ->addMinutes($shift->late_grace_minutes);

        return [
            'status' => $checkIn->gt($cutoff) ? 'late' : 'present',
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'hours_worked' => $checkOut ? round($checkIn->floatDiffInRealHours($checkOut), 2) : null,
        ];
    }

    /**
     * Per-employee, per-day status grid for the given date range.
     *
     * @return array<int, array<string, array{status: string, check_in: ?Carbon, check_out: ?Carbon, hours_worked: ?float}>>
     */
    public function buildReport(Collection $employees, Carbon $start, Carbon $end, AttendanceSetting $settings): array
    {
        $logsByEmployeeAndDay = AttendanceLog::whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('punched_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->orderBy('punched_at')
            ->get()
            ->groupBy(fn (AttendanceLog $log) => $log->employee_id . '|' . $log->punched_at->toDateString());

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
     * Today's present / late / absent counts for the dashboard info-boxes.
     *
     * @return array{present: int, late: int, absent: int, total: int}
     */
    public function todaySummary(Collection $employees, AttendanceSetting $settings): array
    {
        $today = Carbon::today();
        $report = $this->buildReport($employees, $today, $today, $settings);

        $counts = ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'total' => $employees->count()];

        foreach ($report as $days) {
            $status = $days[$today->toDateString()]['status'];

            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }

        return $counts;
    }

    /**
     * Present vs absent counts for the last N days, for the trend chart.
     *
     * @return array<int, array{date: string, present: int, absent: int}>
     */
    public function trend(Collection $employees, AttendanceSetting $settings, int $days = 7): array
    {
        $end = Carbon::today();
        $start = $end->copy()->subDays($days - 1);

        $report = $this->buildReport($employees, $start, $end, $settings);

        $trend = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();
            $present = 0;
            $absent = 0;

            foreach ($report as $employeeDays) {
                $status = $employeeDays[$key]['status'];

                if (in_array($status, ['present', 'late'], true)) {
                    $present++;
                } elseif ($status === 'absent') {
                    $absent++;
                }
            }

            $trend[] = ['date' => $key, 'present' => $present, 'absent' => $absent];
        }

        return $trend;
    }
}
