@extends('adminlte::page')

@section('title', 'Employees')

@section('content_header')
<x-attendance.header title="Employees" icon="fas fa-id-badge" subtitle="Staff linked to fingerprint devices" />
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card card-outline card-primary">

    <div class="card-header d-flex justify-content-between align-items-center">

        <h3 class="card-title">Employee Roster</h3>

        <a href="{{ route('attendance.employees.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-user-plus"></i> Add Employee
        </a>

    </div>

    <div class="card-body p-0">

        <table class="table table-bordered table-striped data-table mb-0">

            <thead>
                <tr>
                    <th>Name</th>
                    <th>Designation</th>
                    <th>Phone</th>
                    <th>Device PIN</th>
                    <th>Zone</th>
                    <th>Duty Shift</th>
                    <th>Weekly Off</th>
                    <th>Status</th>
                    <th width="140">Actions</th>
                </tr>
            </thead>

            <tbody>

                @forelse($employees as $employee)

                    <tr>
                        <td>{{ $employee->name }}</td>
                        <td>{{ $employee->designation ?? '-' }}</td>
                        <td>{{ $employee->phone ?? '-' }}</td>
                        <td>
                            @if($employee->device_user_id)
                                <span class="badge badge-info">{{ $employee->device_user_id }}</span>
                            @else
                                <span class="badge badge-secondary">Not Linked</span>
                            @endif
                        </td>
                        <td>{{ $employee->zone ?? 'All Zones' }}</td>
                        <td>{{ $employee->dutyShift->name ?? 'Default Shift' }}</td>
                        <td>
                            {{ $employee->weekly_off_day ?? $employee->effectiveWeeklyOffDay() . ' (default)' }}
                        </td>
                        <td>
                            @if($employee->status)
                                <span class="badge badge-success">ACTIVE</span>
                            @else
                                <span class="badge badge-danger">INACTIVE</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('attendance.employees.edit', $employee->id) }}" class="btn btn-warning btn-sm">
                                Edit
                            </a>

                            @if(strtolower(auth()->user()->role) == 'admin')
                                <form action="{{ route('attendance.employees.destroy', $employee->id) }}"
                                      method="POST"
                                      class="d-inline js-confirm-delete"
                                      data-confirm-message="Remove employee {{ $employee->name }}?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        Delete
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>

                @empty

                    <tr>
                        <td colspan="9" class="text-center">No employees yet</td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>

@stop
