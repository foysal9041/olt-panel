@php
    $shift = $shift ?? null;
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
        <label>Shift Name</label>
        <input type="text" name="name" class="form-control"
               value="{{ old('name', $shift->name ?? '') }}"
               placeholder="e.g. Morning Shift" required>
    </div>

    <div class="form-group">
        <label>Start Time</label>
        <input type="time" name="start_time" class="form-control"
               value="{{ old('start_time', $shift ? \Illuminate\Support\Carbon::parse($shift->start_time)->format('H:i') : '09:00') }}"
               required>
    </div>

    <div class="form-group">
        <label>Late Grace Period (minutes)</label>
        <input type="number" min="0" max="120" name="late_grace_minutes" class="form-control"
               value="{{ old('late_grace_minutes', $shift->late_grace_minutes ?? 15) }}"
               required>
    </div>

    <div class="form-group form-check">
        <input type="checkbox" name="is_default" id="is_default" class="form-check-input" value="1"
               {{ old('is_default', $shift->is_default ?? false) ? 'checked' : '' }}>
        <label class="form-check-label" for="is_default">
            Use as default shift for employees without one assigned
        </label>
    </div>

</div>
