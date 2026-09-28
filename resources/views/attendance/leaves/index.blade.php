@extends('adminlte::page')

@section('title', 'Leave Management')

@section('content_header')
<h1>Leave Management</h1>
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card card-outline card-primary">

    <div class="card-header">
        <h3 class="card-title">Filters</h3>
    </div>

    <div class="card-body">

        <form method="GET" action="{{ route('attendance.leaves.index') }}">

            <div class="row">

                <div class="col-md-4">
                    <label>Employee</label>
                    <select name="employee_id" class="form-control">
                        <option value="">All Employees</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" {{ request('employee_id') == $employee->id ? 'selected' : '' }}>
                                {{ $employee->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="">All</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
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
        <h3 class="card-title">Leave Records</h3>
        <a href="{{ route('attendance.leaves.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Leave
        </a>
    </div>

    <div class="card-body p-0">

        <table class="table table-bordered table-striped data-table mb-0">

            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Type</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Days</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th width="220">Actions</th>
                </tr>
            </thead>

            <tbody>

                @forelse($leaves as $leave)

                    <tr>
                        <td>{{ $leave->employee->name }}</td>
                        <td><span class="badge badge-secondary">{{ $leave->leaveType->name }}</span></td>
                        <td>{{ $leave->start_date->format('d M Y') }}</td>
                        <td>{{ $leave->end_date->format('d M Y') }}</td>
                        <td>{{ $leave->daysCount() }}</td>
                        <td>{{ $leave->reason ?? '-' }}</td>
                        <td>
                            @if($leave->status == 'approved')
                                <span class="badge badge-success">APPROVED</span>
                            @elseif($leave->status == 'rejected')
                                <span class="badge badge-danger">REJECTED</span>
                            @else
                                <span class="badge badge-warning">PENDING</span>
                            @endif
                        </td>
                        <td>

                            @if($leave->status == 'pending')

                                <form action="{{ route('attendance.leaves.approve', $leave->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                </form>

                                <form action="{{ route('attendance.leaves.reject', $leave->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-warning btn-sm">Reject</button>
                                </form>

                            @endif

                            <a href="{{ route('attendance.leaves.edit', $leave->id) }}" class="btn btn-info btn-sm">
                                Edit
                            </a>

                            @if(strtolower(auth()->user()->role) == 'admin')
                                <form action="{{ route('attendance.leaves.destroy', $leave->id) }}"
                                      method="POST"
                                      class="d-inline js-confirm-delete"
                                      data-confirm-message="Delete this leave record?">
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
                        <td colspan="8" class="text-center">No leave records yet</td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>

<div class="card card-outline card-info">

    <div class="card-header">
        <h3 class="card-title">Leave Types</h3>
    </div>

    <div class="card-body">

        <div class="mb-3">
            @foreach($leaveTypes as $type)
                <span class="badge badge-info p-2 mr-1 mb-1">
                    {{ $type->name }}
                    @if($type->default_days_per_year)
                        ({{ $type->default_days_per_year }} days/yr)
                    @endif

                    <form action="{{ route('attendance.leave-types.destroy', $type->id) }}"
                          method="POST"
                          class="d-inline ml-1 js-confirm-delete"
                          data-confirm-message="Remove leave type {{ $type->name }}?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-link text-white p-0" style="text-decoration:none;">&times;</button>
                    </form>
                </span>
            @endforeach
        </div>

        <form method="POST" action="{{ route('attendance.leave-types.store') }}" class="form-inline">
            @csrf

            <input type="text" name="name" class="form-control mr-2 mb-2" placeholder="e.g. Maternity Leave" required>

            <input type="number" name="default_days_per_year" class="form-control mr-2 mb-2" placeholder="Days/year (optional)" min="0" max="365" style="width: 180px;">

            <button type="submit" class="btn btn-info mb-2">
                <i class="fas fa-plus"></i> Add Leave Type
            </button>
        </form>

    </div>

</div>

@stop
