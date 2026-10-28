@extends('adminlte::page')

@section('title', 'Attendance Report')

@section('content_header')
<x-attendance.header title="Attendance Report" icon="fas fa-calendar-check" subtitle="Daily check-in, check-out and hours by employee" />
@stop

@section('content')

<div class="card card-primary card-outline">

    <div class="card-header">
        <h3 class="card-title">Filters</h3>
    </div>

    <div class="card-body">

        <form method="GET" action="{{ route('attendance.report') }}">

            <div class="row">

                <div class="col-md-3">
                    <label>From</label>
                    <input type="date" name="start" class="form-control" value="{{ $start->toDateString() }}">
                </div>

                <div class="col-md-3">
                    <label>To</label>
                    <input type="date" name="end" class="form-control" value="{{ $end->toDateString() }}">
                </div>

                <div class="col-md-4">
                    <label>Employee</label>
                    <select name="employee_id" class="form-control">
                        <option value="">All Employees</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" {{ $selectedEmployee && $selectedEmployee->id == $employee->id ? 'selected' : '' }}>
                                {{ $employee->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                </div>

            </div>

        </form>

    </div>

</div>

<div class="card card-outline card-primary">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-users mr-1"></i> Summary — {{ $start->format('d M Y') }} to {{ $end->format('d M Y') }}</h3>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th class="pl-3">Employee</th>
                    <th class="text-center">Present</th>
                    <th class="text-center">Late days</th>
                    <th class="text-right">Late time</th>
                    <th class="text-center">Absent</th>
                    <th class="text-center">On Leave</th>
                    <th class="text-center" title="Weekly off days they came in">Off days worked</th>
                    <th class="text-right" title="After the shift's end on working days, plus whole weekly off days">Extra duty</th>
                    <th class="text-right pr-3">Hours worked</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($reportEmployees as $employee)
                    @php $t = $totals[$employee->id] ?? null; @endphp
                    @continue(! $t)
                    <tr>
                        <td class="pl-3">{{ $employee->name }}</td>
                        <td class="text-center">{{ $t['present'] }}</td>
                        <td class="text-center">{{ $t['late'] ?: '' }}</td>
                        <td class="text-right {{ $t['late_minutes'] ? 'text-warning font-weight-bold' : '' }}">{{ $t['late_minutes'] ? \App\Support\Ui::minutes($t['late_minutes']) : '' }}</td>
                        <td class="text-center {{ $t['absent'] ? 'text-danger' : '' }}">{{ $t['absent'] ?: '' }}</td>
                        <td class="text-center">{{ $t['leave'] ?: '' }}</td>
                        <td class="text-center {{ $t['overtime_days'] ? 'text-primary font-weight-bold' : '' }}">{{ $t['overtime_days'] ?: '' }}</td>
                        <td class="text-right">
                            @if ($t['extra_minutes'])
                                <span class="text-primary font-weight-bold">{{ \App\Support\Ui::minutes($t['extra_minutes']) }}</span>
                                @if ($t['extra_offday'] && $t['extra_shift'])
                                    <div class="small text-muted">{{ \App\Support\Ui::minutes($t['extra_shift']) }} + {{ \App\Support\Ui::minutes($t['extra_offday']) }} {{ \App\Support\Ui::t('off day') }}</div>
                                @endif
                            @endif
                        </td>
                        <td class="text-right pr-3">{{ $t['hours'] ? number_format($t['hours'], 1) . ' hrs' : '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">
        <i class="fas fa-info-circle"></i> Check-in is the day's first punch and check-out its last. Late time runs from the shift's start to the check-in, on days past the grace period. Extra duty is the time from the shift's end to the check-out, and on the weekly off day all of that day's hours.
        @if ($officeTime)
            <div class="mt-1">
                <i class="fas fa-clock"></i>
                {{ \App\Support\Ui::t('Office time:') }} <strong>{{ $officeTime }}</strong> —
                {{ \App\Support\Ui::t('punches outside it are not counted.') }}
            </div>
        @endif
        @if ($shiftsWithoutEnd->isNotEmpty())
            <div class="text-warning mt-1">
                <i class="fas fa-exclamation-triangle"></i>
                {{ \App\Support\Ui::t('No end time set for these shifts, so their extra duty after the shift is not counted:') }}
                <strong>{{ $shiftsWithoutEnd->implode(', ') }}</strong>
                @can('access-attendance-shifts')
                    —
                    <a href="{{ route('attendance.shifts.index') }}">{{ \App\Support\Ui::t('Set end times') }}</a>
                @endcan
            </div>
        @endif
    </div>
</div>

<div class="card card-outline card-secondary">

    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">
            Daily Status — {{ $start->format('d M Y') }} to {{ $end->format('d M Y') }}
        </h3>
        <a href="{{ route('attendance.report.export', request()->query()) }}" class="btn btn-success btn-sm">
            <i class="fas fa-file-csv"></i> Export CSV
        </a>
    </div>

    <div class="card-body">

        <table class="table table-bordered table-striped data-table">

            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Date</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Hours Worked</th>
                    <th>Late</th>
                    <th>Extra Duty</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

                @forelse($reportEmployees as $employee)

                    @foreach($report[$employee->id] as $date => $info)

                        <tr>
                            <td>{{ $employee->name }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($date)->format('d M Y (D)') }}</td>
                            <td>{{ $info['check_in']?->format('h:i A') ?? '-' }}</td>
                            <td>{{ $info['check_out']?->format('h:i A') ?? '-' }}</td>
                            <td>{{ $info['hours_worked'] !== null ? $info['hours_worked'] . ' hrs' : '-' }}</td>
                            <td data-order="{{ $info['late_minutes'] ?? 0 }}" class="{{ $info['late_minutes'] ? 'text-warning font-weight-bold' : '' }}">{{ $info['late_minutes'] ? \App\Support\Ui::minutes($info['late_minutes']) : '-' }}</td>
                            <td data-order="{{ $info['extra_minutes'] ?? 0 }}" class="{{ $info['extra_minutes'] ? 'text-primary font-weight-bold' : '' }}">{{ $info['extra_minutes'] ? \App\Support\Ui::minutes($info['extra_minutes']) : '-' }}</td>
                            <td>
                                @switch($info['status'])
                                    @case('present')
                                        <span class="badge badge-success">PRESENT</span>
                                        @break
                                    @case('late')
                                        <span class="badge badge-warning">LATE</span>
                                        @break
                                    @case('absent')
                                        <span class="badge badge-danger">ABSENT</span>
                                        @break
                                    @case('weekend')
                                        <span class="badge badge-secondary">WEEKLY OFF</span>
                                        @break
                                    @case('overtime')
                                        <span class="badge badge-primary" title="Came in on the weekly off day — counted as overtime">OVERTIME</span>
                                        @break
                                    @case('leave')
                                        <span class="badge badge-info">ON LEAVE</span>
                                        @break
                                    @default
                                        <span class="badge badge-light">-</span>
                                @endswitch
                            </td>
                        </tr>

                    @endforeach

                @empty

                    <tr>
                        <td colspan="8" class="text-center">No employees linked to a device yet</td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>

@stop
