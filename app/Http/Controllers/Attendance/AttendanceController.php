<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\AttendanceSetting;
use App\Models\DutyShift;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\ZkDevice;
use App\Services\AttendanceCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceCalculator $calculator)
    {
    }

    public function dashboard()
    {
        $settings = AttendanceSetting::current();
        $employees = $this->employeesQuery()->ordered()->get();

        $summary = $this->calculator->todaySummary($employees, $settings);

        // The last 7 days (today included): the chart, the week's totals and today's board.
        $today = Carbon::today();
        $weekReport = $this->calculator->buildReport($employees, $today->copy()->subDays(6), $today, $settings);
        $trend = $this->calculator->trend($employees, $settings, 7, $weekReport);
        $weekTotals = collect($this->calculator->totals($weekReport));
        $present = array_sum(array_column($trend, 'present'));
        $expected = array_sum(array_column($trend, 'expected'));
        $byId = $employees->keyBy('id');
        $top = fn (string $field) => $weekTotals->filter(fn ($t) => $t[$field] > 0)->sortByDesc($field)->take(3)
            ->map(fn ($t, $id) => ['employee' => $byId[$id]] + $t)->values();
        $week = [
            'rate' => $expected ? (int) round($present / $expected * 100) : null,
            'present' => $present,
            'expected' => $expected,
            'late_days' => $weekTotals->sum('late'),
            'late_minutes' => $weekTotals->sum('late_minutes'),
            'absent' => $weekTotals->sum('absent'),
            'extra_minutes' => $weekTotals->sum('extra_minutes'),
            'overtime_days' => $weekTotals->sum('overtime_days'),
            'most_late' => $top('late_minutes'),
            'most_extra' => $top('extra_minutes'),
        ];

        $recentPunches = AttendanceLog::with(['employee', 'zkDevice'])
            ->whereIn('employee_id', $employees->pluck('id'))
            ->latest('punched_at')
            ->limit(15)
            ->get();

        // IN / OUT on those punches: each employee's first and last punch of
        // that attendance day; 'ignored' for one outside office time.
        $punchRoles = [];
        foreach ($recentPunches as $punch) {
            if ($settings->workDay($punch->punched_at) === null) {
                $punchRoles[$punch->id] = 'ignored';
            }
        }
        $counted = $recentPunches->reject(fn ($p) => isset($punchRoles[$p->id]));
        foreach ($counted->groupBy(fn ($p) => $p->employee_id . '|' . $settings->workDay($p->punched_at))->keys() as $key) {
            [$employeeId, $date] = explode('|', $key);
            [$in, $out] = AttendanceCalculator::inAndOut(
                AttendanceLog::where('employee_id', $employeeId)
                    ->whereBetween('punched_at', $settings->workDayRange(Carbon::parse($date)))
                    ->get(['id', 'punched_at'])
                    ->filter(fn ($p) => $settings->workDay($p->punched_at) === $date)
            );
            if ($in) {
                $punchRoles[$in->id] = 'in';
            }
            if ($out) {
                $punchRoles[$out->id] = 'out';
            }
        }

        // Today's board: one row per employee with status and times.
        $board = $employees->map(fn (Employee $employee) => [
            'employee' => $employee,
        ] + ($weekReport[$employee->id][$today->toDateString()] ?? ['status' => 'absent', 'check_in' => null, 'check_out' => null, 'hours_worked' => null, 'late_minutes' => null, 'extra_minutes' => null]))
            ->values(); // in serial number order, like the employee list

        $devices = ZkDevice::orderBy('name')->get();

        $pendingLeaves = Leave::with(['employee', 'leaveType'])
            ->where('status', 'pending')
            ->whereIn('employee_id', $employees->pluck('id'))
            ->orderBy('start_date')
            ->limit(6)
            ->get();

        return view('attendance.dashboard', compact(
            'summary',
            'trend',
            'week',
            'recentPunches',
            'punchRoles',
            'employees',
            'board',
            'devices',
            'pendingLeaves',
            'settings'
        ));
    }

    public function report(Request $request)
    {
        [$report, $reportEmployees, $employees, $start, $end, $selectedEmployee] = $this->buildReportData($request);
        $totals = $this->calculator->totals($report);

        // Shifts in this report with no end time yet: their extra duty after the shift can't be counted.
        $defaultShift = DutyShift::default();
        $shiftsWithoutEnd = $reportEmployees->map(fn (Employee $e) => $e->dutyShift ?? $defaultShift)
            ->filter(fn (DutyShift $s) => ! $s->end_time)
            ->unique('id')
            ->pluck('name');
        $officeTime = AttendanceSetting::current()->punchWindowLabel();

        return view('attendance.report', compact(
            'report',
            'totals',
            'shiftsWithoutEnd',
            'officeTime',
            'reportEmployees',
            'employees',
            'start',
            'end',
            'selectedEmployee'
        ));
    }

    public function exportReport(Request $request): StreamedResponse
    {
        [$report, $reportEmployees, , $start, $end] = $this->buildReportData($request);

        $filename = 'attendance-report_' . $start->toDateString() . '_to_' . $end->toDateString() . '.csv';

        return response()->streamDownload(function () use ($report, $reportEmployees) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Employee', 'Date', 'Check In', 'Check Out', 'Hours Worked', 'Late (minutes)', 'Extra Duty (minutes)', 'Status']);

            foreach ($reportEmployees as $employee) {
                foreach ($report[$employee->id] as $date => $info) {
                    fputcsv($out, [
                        $employee->name,
                        Carbon::parse($date)->format('d M Y (D)'),
                        $info['check_in']?->format('h:i A') ?? '-',
                        $info['check_out']?->format('h:i A') ?? '-',
                        $info['hours_worked'] ?? '-',
                        $info['late_minutes'] ?? '',
                        $info['extra_minutes'] ?? '',
                        $info['status'] === 'overtime' ? 'Overtime (weekly off)' : ucfirst($info['status']),
                    ]);
                }
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function absence(Request $request)
    {
        [$employees, $absenceCounts, $leaveCounts, $month, $start, $end] = $this->buildAbsenceData($request);

        return view('attendance.absence', compact(
            'employees',
            'absenceCounts',
            'leaveCounts',
            'month',
            'start',
            'end'
        ));
    }

    public function exportAbsence(Request $request): StreamedResponse
    {
        [$employees, $absenceCounts, $leaveCounts, $month] = $this->buildAbsenceData($request);

        $filename = 'absence-report_' . $month->format('Y-m') . '.csv';

        return response()->streamDownload(function () use ($employees, $absenceCounts, $leaveCounts) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Employee', 'Designation', 'Zone', 'Days Absent', 'Days On Leave']);

            foreach ($employees as $employee) {
                fputcsv($out, [
                    $employee->name,
                    $employee->designation ?? '-',
                    $employee->zone ?? 'All Zones',
                    $absenceCounts[$employee->id] ?? 0,
                    $leaveCounts[$employee->id] ?? 0,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'weekly_off_day' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'punch_from' => 'nullable|date_format:H:i|required_with:punch_until',
            'punch_until' => 'nullable|date_format:H:i|required_with:punch_from|different:punch_from',
        ], [], ['punch_from' => 'office time start', 'punch_until' => 'office time end']);

        AttendanceSetting::current()->update([
            'weekly_off_day' => $request->weekly_off_day,
            'punch_from' => $request->punch_from ?: null,
            'punch_until' => $request->punch_until ?: null,
        ]);

        return redirect()
            ->route('attendance.devices.index')
            ->with('success', 'Attendance Settings Updated Successfully');
    }

    /**
     * @return array{0: array, 1: \Illuminate\Support\Collection, 2: \Illuminate\Support\Collection, 3: Carbon, 4: Carbon, 5: ?Employee}
     */
    private function buildReportData(Request $request): array
    {
        $settings = AttendanceSetting::current();
        $employees = $this->employeesQuery()->ordered()->get();

        $start = $request->filled('start')
            ? Carbon::parse($request->start)
            : Carbon::today()->startOfMonth();

        $end = $request->filled('end')
            ? Carbon::parse($request->end)
            : Carbon::today();

        if ($end->lt($start)) {
            $end = $start->copy();
        }

        if ($start->diffInDays($end) > 62) {
            $end = $start->copy()->addDays(62);
        }

        $selectedEmployee = $request->filled('employee_id')
            ? $employees->firstWhere('id', (int) $request->employee_id)
            : null;

        $reportEmployees = $selectedEmployee
            ? collect([$selectedEmployee])
            : $employees;

        $report = $this->calculator->buildReport($reportEmployees, $start, $end, $settings);

        return [$report, $reportEmployees, $employees, $start, $end, $selectedEmployee];
    }

    /**
     * @return array{0: \Illuminate\Support\Collection, 1: array, 2: array, 3: Carbon, 4: Carbon, 5: Carbon}
     */
    private function buildAbsenceData(Request $request): array
    {
        $settings = AttendanceSetting::current();
        $employees = $this->employeesQuery()->ordered()->get();

        $month = $request->filled('month')
            ? Carbon::parse($request->month . '-01')
            : Carbon::today()->startOfMonth();

        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        if ($end->gt(Carbon::today())) {
            $end = Carbon::today();
        }

        $report = $this->calculator->buildReport($employees, $start, $end, $settings);

        $absenceCounts = [];
        $leaveCounts = [];

        foreach ($report as $employeeId => $days) {
            $absenceCounts[$employeeId] = collect($days)->where('status', 'absent')->count();
            $leaveCounts[$employeeId] = collect($days)->where('status', 'leave')->count();
        }

        arsort($absenceCounts);

        return [$employees, $absenceCounts, $leaveCounts, $month, $start, $end];
    }

    private function employeesQuery()
    {
        $user = auth()->user();

        $query = Employee::whereNotNull('device_user_id')->with('dutyShift');

        if (! $user->seesAllZones()) {
            $query->where('zone', $user->zone);
        }

        return $query;
    }
}
