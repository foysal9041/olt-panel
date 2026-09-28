@extends('adminlte::page')

@section('title', 'Duty Shifts')

@section('content_header')
<x-attendance.header title="Duty Shifts" icon="fas fa-business-time" subtitle="Working hours and late grace per shift" />
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card card-outline card-primary">

    <div class="card-header d-flex justify-content-between align-items-center">

        <h3 class="card-title">Shifts</h3>

        @if(strtolower(auth()->user()->role) == 'admin')
            <a href="{{ route('attendance.shifts.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Add Shift
            </a>
        @endif

    </div>

    <div class="card-body p-0">

        <table class="table table-bordered table-striped mb-0">

            <thead>
                <tr>
                    <th>Name</th>
                    <th>Start Time</th>
                    <th>Late Grace</th>
                    <th>Employees</th>
                    <th>Default</th>
                    @if(strtolower(auth()->user()->role) == 'admin')
                        <th width="160">Actions</th>
                    @endif
                </tr>
            </thead>

            <tbody>

                @forelse($shifts as $shift)

                    <tr>
                        <td>{{ $shift->name }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($shift->start_time)->format('h:i A') }}</td>
                        <td>{{ $shift->late_grace_minutes }} min</td>
                        <td>{{ $shift->employees_count }}</td>
                        <td>
                            @if($shift->is_default)
                                <span class="badge badge-primary">DEFAULT</span>
                            @endif
                        </td>
                        @if(strtolower(auth()->user()->role) == 'admin')
                            <td>
                                <a href="{{ route('attendance.shifts.edit', $shift->id) }}" class="btn btn-warning btn-sm">
                                    Edit
                                </a>

                                <form action="{{ route('attendance.shifts.destroy', $shift->id) }}"
                                      method="POST"
                                      class="d-inline js-confirm-delete"
                                      data-confirm-message="Delete shift {{ $shift->name }}?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        @endif
                    </tr>

                @empty

                    <tr>
                        <td colspan="6" class="text-center">No duty shifts yet</td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>

@stop
