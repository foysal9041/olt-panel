@extends('adminlte::page')

@section('title', 'Edit Device')

@section('content_header')
<x-attendance.header title="Edit Device" subtitle="{{ $device->name ?: $device->serial_number }}" :back="route('attendance.devices.index')" />
@stop

@section('content')

<div class="card card-outline card-primary col-md-6">

    <div class="card-header">
        <h3 class="card-title">{{ $device->serial_number }}</h3>
    </div>

    <form method="POST" action="{{ route('attendance.devices.update', $device->id) }}">
        @csrf
        @method('PUT')

        <div class="card-body">

            <div class="form-group">
                <label>Display Name</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $device->name) }}" placeholder="e.g. Head Office F18">
                @error('name')
                    <div class="text-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label>Serial Number</label>
                <input type="text" class="form-control" value="{{ $device->serial_number }}" disabled>
            </div>

            <div class="form-group">
                <label>Zone / Location</label>
                <input type="text" name="zone" class="form-control" value="{{ old('zone', $device->zone) }}" placeholder="e.g. Head Office">
                @error('zone')
                    <div class="text-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label>Clock Offset (minutes)</label>
                <input type="number" step="1" name="clock_offset_minutes" class="form-control"
                       value="{{ old('clock_offset_minutes', $device->clock_offset_minutes) }}">
                <small class="text-muted">
                    Only needed if this device's own clock can't be fixed (e.g. no timezone setting, or it drifts
                    every time it reconnects). Enter how many minutes <strong>ahead</strong> of the real time it
                    reports — e.g. <code>120</code> if it always shows 2 hours ahead, <code>-120</code> if 2 hours
                    behind. This is subtracted from every punch time before it's stored. Leave at <code>0</code>
                    if the device's clock is reliable.
                </small>
                @error('clock_offset_minutes')
                    <div class="text-danger">{{ $message }}</div>
                @enderror
            </div>

        </div>

        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('attendance.devices.index') }}" class="btn btn-secondary">Cancel</a>
        </div>

    </form>

</div>

@stop
