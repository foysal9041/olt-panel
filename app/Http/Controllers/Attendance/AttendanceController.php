<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\AttendanceSetting;
use App\Models\Employee;
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
        $employees = $this->employeesQuery()->orderBy('name')->get();

        $summary = $this->calculator->todaySummary($employees, $settings);
        $trend = $this->calculator->trend($employees, $settings, 7);

        $recentPunches = AttendanceLog::with(['employee', 'zkDevice'])
            ->whereIn('employee_id', $employees->pluck('id'))
            ->latest('punched_at')
            ->limit(15)
            ->get();

        $unmappedPunches = AttendanceLog::with('zkDevice')
            ->whereNull('employee_id')
            ->selectRaw('zk_device_id, device_user_id, COUNT(*) as punches_count, MAX(punched_at) as last_punched_at')
            ->groupBy('zk_device_id', 'device_user_id')
            ->orderByDesc('last_punched_at')
            ->limit(20)
            ->get();

        $unmappedCount = $unmappedPunches->count();

        return view('attendance.dashboard', compact(
            'summary',
            'trend',
            'recentPunches',
            'employees',
            'unmappedCount',
            'unmappedPunches'
        ));
    }

    public function report(Request $request)
    {
        [$report, $reportEmployees, $employees, $start, $end, $selectedEmployee] = $this->buildReportData($request);

        return view('attendance.report', compact(
            'report',
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

            fputcsv($out, ['Employee', 'Date', 'Check In', 'Check Out', 'Hours Worked', 'Status']);

            foreach ($reportEmployees as $employee) {
                foreach ($report[$employee->id] as $date => $info) {
                    fputcsv($out, [
                        $employee->name,
                        Carbon::parse($date)->format('d M Y (D)'),
                        $info['check_in']?->format('h:i A') ?? '-',
                        $info['check_out']?->format('h:i A') ?? '-',
                        $info['hours_worked'] ?? '-',
                        ucfirst($info['status']),
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
        if (strtolower(auth()->user()->role) !== 'admin') {
            abort(403);
        }

        $request->validate([
            'weekly_off_day' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
        ]);

        AttendanceSetting::current()->update($request->only([
            'weekly_off_day',
        ]));

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
        $employees = $this->employeesQuery()->orderBy('name')->get();

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
        $employees = $this->employeesQuery()->orderBy('name')->get();

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

        if (!in_array(strtolower($user->role), ['admin', 'noc'])) {
            $query->where('zone', $user->zone);
        }

        return $query;
    }
}
