@php
    $employee = $employee ?? null;
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
        <label>Name</label>
        <input type="text" name="name" class="form-control"
               value="{{ old('name', $employee->name ?? '') }}" required autofocus>
    </div>

    <div class="form-group">
        <label>Phone</label>
        <input type="text" name="phone" class="form-control"
               value="{{ old('phone', $employee->phone ?? '') }}">
    </div>

    <div class="form-group">
        <label>Designation</label>
        <input type="text" name="designation" class="form-control"
               value="{{ old('designation', $employee->designation ?? '') }}"
               placeholder="e.g. Network Engineer">
    </div>

    <div class="form-group">
        <label>Device PIN (ZKTeco F18)</label>
        <input type="text" name="device_user_id" class="form-control"
               value="{{ old('device_user_id', $employee->device_user_id ?? request('device_user_id', '')) }}"
               placeholder="PIN configured on the attendance device">
        <small class="text-muted">Optional — can be linked later once the employee is enrolled on the device.</small>
    </div>

    <div class="form-group">
        <label>Zone</label>
        <select name="zone" class="form-control select2-zone">
            <option value="">All Zones</option>
            @foreach($zones as $zone)
                <option value="{{ $zone }}" {{ old('zone', $employee->zone ?? '') == $zone ? 'selected' : '' }}>
                    {{ $zone }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label>Duty Shift</label>
        <select name="duty_shift_id" class="form-control">
            <option value="">Default Shift</option>
            @foreach($shifts as $shift)
                <option value="{{ $shift->id }}" {{ old('duty_shift_id', $employee->duty_shift_id ?? '') == $shift->id ? 'selected' : '' }}>
                    {{ $shift->name }} ({{ \Illuminate\Support\Carbon::parse($shift->start_time)->format('h:i A') }})
                </option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label>Weekly Off Day</label>
        <select name="weekly_off_day" class="form-control">
            <option value="">Use Company Default ({{ \App\Models\AttendanceSetting::current()->weekly_off_day }})</option>
            @foreach(['Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'] as $day)
                <option value="{{ $day }}" {{ old('weekly_off_day', $employee->weekly_off_day ?? '') == $day ? 'selected' : '' }}>
                    {{ $day }}
                </option>
            @endforeach
        </select>
        <small class="text-muted">Only set this if this employee's rest day is different from the company default.</small>
    </div>

    <div class="form-group">
        <label>Leave Balances (days per year)</label>
        <div class="card card-body bg-light">

            <div class="row font-weight-bold mb-2 d-none d-md-flex">
                <div class="col-md-4">Leave Type</div>
                <div class="col-md-3">Days Allocated</div>
                @if($employee)
                    <div class="col-md-2">Used</div>
                    <div class="col-md-3">Remaining</div>
                @endif
            </div>

            @foreach($leaveTypes as $type)
                @php
                    $existing = $employee?->leaveBalances->firstWhere('leave_type_id', $type->id);
                    $allocated = old("leave_balances.{$type->id}", $existing->days_allocated ?? $type->default_days_per_year);
                @endphp
                <div class="row align-items-center mb-2">
                    <div class="col-md-4">{{ $type->name }}</div>
                    <div class="col-md-3">
                        <input type="number" min="0" max="365" name="leave_balances[{{ $type->id }}]"
                               class="form-control" placeholder="{{ $type->default_days_per_year ?? 0 }}"
                               value="{{ $allocated }}">
                    </div>
                    @if($employee)
                        <div class="col-md-2">{{ $employee->usedLeaveDays($type) }}</div>
                        <div class="col-md-3">{{ $employee->remainingLeaveDays($type) }}</div>
                    @endif
                </div>
            @endforeach

            <small class="text-muted mt-1">Leave blank to use that leave type's default. "Used" and "Remaining" are for the current year.</small>

        </div>
    </div>

    <div class="form-group form-check">
        <input type="checkbox" name="status" id="status" class="form-check-input" value="1"
               {{ old('status', $employee->status ?? true) ? 'checked' : '' }}>
        <label class="form-check-label" for="status">Active</label>
    </div>

</div>
