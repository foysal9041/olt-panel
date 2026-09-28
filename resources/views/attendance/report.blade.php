@extends('adminlte::page')

@section('title', 'Attendance Report')

@section('content_header')
<h1>Attendance Report</h1>
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
                        <td colspan="6" class="text-center">No employees linked to a device yet</td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>

@stop
