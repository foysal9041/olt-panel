@php
    $leave = $leave ?? null;
@endphp

<div class="card-body">

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="form-group">
        <label>Employee</label>
        <select name="employee_id" class="form-control" required>
            <option value="">Select an employee</option>
            @foreach($employees as $employee)
                <option value="{{ $employee->id }}" {{ old('employee_id', $leave->employee_id ?? '') == $employee->id ? 'selected' : '' }}>
                    {{ $employee->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label>Leave Type</label>
        <select name="leave_type_id" class="form-control" required>
            <option value="">Select a leave type</option>
            @foreach($leaveTypes as $type)
                <option value="{{ $type->id }}" {{ old('leave_type_id', $leave->leave_type_id ?? '') == $type->id ? 'selected' : '' }}>
                    {{ $type->name }}
                </option>
            @endforeach
        </select>
        <small class="text-muted">
            Need a new type? Add one from the <a href="{{ route('attendance.leaves.index') }}">Leave Management</a> page.
        </small>
    </div>

    <div class="row">

        <div class="col-md-6">
            <div class="form-group">
                <label>From</label>
                <input type="date" name="start_date" class="form-control"
                       value="{{ old('start_date', $leave ? $leave->start_date->toDateString() : '') }}" required>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label>To</label>
                <input type="date" name="end_date" class="form-control"
                       value="{{ old('end_date', $leave ? $leave->end_date->toDateString() : '') }}" required>
            </div>
        </div>

    </div>

    <div class="form-group">
        <label>Reason</label>
        <textarea name="reason" class="form-control" rows="2">{{ old('reason', $leave->reason ?? '') }}</textarea>
    </div>

    <div class="form-group">
        <label>Status</label>
        <select name="status" class="form-control" required>
            <option value="pending" {{ old('status', $leave->status ?? 'pending') == 'pending' ? 'selected' : '' }}>Pending</option>
            <option value="approved" {{ old('status', $leave->status ?? '') == 'approved' ? 'selected' : '' }}>Approved</option>
            <option value="rejected" {{ old('status', $leave->status ?? '') == 'rejected' ? 'selected' : '' }}>Rejected</option>
        </select>
        <small class="text-muted">Only approved leave excludes an employee from the Absence Report on those days.</small>
    </div>

</div>
