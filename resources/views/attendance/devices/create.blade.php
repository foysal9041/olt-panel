@extends('adminlte::page')

@section('title', 'Add Device')

@section('content_header')
<x-attendance.header title="Add Device" subtitle="Register a ZKTeco terminal" :back="route('attendance.devices.index')" />
@stop

@section('content')

<div class="card card-outline card-primary col-md-6">

    <div class="card-header">
        <h3 class="card-title">Device Details</h3>
    </div>

    <form method="POST" action="{{ route('attendance.devices.store') }}">
        @csrf

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
                <label>Display Name</label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}"
                       placeholder="e.g. Head Office F18" required autofocus>
            </div>

            <div class="form-group">
                <label>Serial Number</label>
                <input type="text" name="serial_number" class="form-control" value="{{ old('serial_number') }}"
                       placeholder="Found on the device: Menu &rarr; System &rarr; Device Info" required>
                <small class="text-muted">
                    Must match exactly what the device sends. If it's already connected under a different
                    entry, edit that one instead — this just pre-registers a device before it connects.
                </small>
            </div>

            <div class="form-group">
                <label>Zone</label>
                <select name="zone" class="form-control">
                    <option value="">— None —</option>
                    @foreach($zones as $zone)
                        <option value="{{ $zone }}" @selected(old('zone') === $zone)>{{ $zone }}</option>
                    @endforeach
                </select>
            </div>

        </div>

        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('attendance.devices.index') }}" class="btn btn-secondary">Cancel</a>
        </div>

    </form>

</div>

@stop
