@extends('adminlte::page')

@section('title', 'Absence Report')

@section('content_header')
<h1>Absence Report</h1>
@stop

@section('content')

<div class="card card-primary card-outline">

    <div class="card-header">
        <h3 class="card-title">Month</h3>
    </div>

    <div class="card-body">

        <form method="GET" action="{{ route('attendance.absence') }}">

            <div class="row">

                <div class="col-md-3">
                    <input type="month" name="month" class="form-control" value="{{ $month->format('Y-m') }}">
                </div>

                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                </div>

            </div>

        </form>

    </div>

</div>

<div class="card card-outline card-danger">

    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">
            Absence Summary — {{ $month->format('F Y') }}
            ({{ $start->format('d M') }} to {{ $end->format('d M') }})
        </h3>
        <a href="{{ route('attendance.absence.export', ['month' => $month->format('Y-m')]) }}" class="btn btn-success btn-sm">
            <i class="fas fa-file-csv"></i> Export CSV
        </a>
    </div>

    <div class="card-body">

        <table class="table table-bordered table-striped data-table">

            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Designation</th>
                    <th>Zone</th>
                    <th>Days Absent</th>
                    <th>Days On Leave</th>
                </tr>
            </thead>

            <tbody>

                @forelse($employees as $employee)

                    <tr>
                        <td>{{ $employee->name }}</td>
                        <td>{{ $employee->designation ?? '-' }}</td>
                        <td>{{ $employee->zone ?? 'All Zones' }}</td>
                        <td>
                            @php $count = $absenceCounts[$employee->id] ?? 0; @endphp

                            @if($count > 0)
                                <span class="badge badge-danger">{{ $count }} day(s)</span>
                            @else
                                <span class="badge badge-success">No Absence</span>
                            @endif
                        </td>
                        <td>
                            @php $leaveDays = $leaveCounts[$employee->id] ?? 0; @endphp

                            @if($leaveDays > 0)
                                <span class="badge badge-info">{{ $leaveDays }} day(s)</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                    </tr>

                @empty

                    <tr>
                        <td colspan="5" class="text-center">No employees linked to a device yet</td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>

@stop
