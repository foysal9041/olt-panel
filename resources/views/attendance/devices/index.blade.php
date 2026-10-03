@extends('adminlte::page')

@section('title', 'Attendance Devices')

@section('content_header')
<x-attendance.header title="Attendance Devices" icon="fas fa-microchip" subtitle="ZKTeco F18 terminals and punch sync" />
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

<div class="row">

    <div class="col-md-7">

        <div class="card card-outline card-primary">

            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title">Registered Devices</h3>

                <a href="{{ route('attendance.devices.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Add Device
                </a>
            </div>

            <div class="card-body p-0">

                <table class="table table-bordered table-striped mb-0">

                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Zone</th>
                            <th>Serial Number</th>
                            <th>Last IP</th>
                            <th>Last Seen</th>
                            <th>Status</th>
                            <th width="220">Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        @forelse($devices as $device)

                            <tr>
                                <td>{{ $device->name ?? 'F18' }}</td>
                                <td>{{ $device->zone ?? '-' }}</td>
                                <td>{{ $device->serial_number }}</td>
                                <td>{{ $device->ip_address ?? '-' }}</td>
                                <td>{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                                <td>
                                    @if($device->isOnline())
                                        <span class="badge badge-success">ONLINE</span>
                                    @else
                                        <span class="badge badge-danger">OFFLINE</span>
                                    @endif

                                    @if($device->hasPendingCommand())
                                        <span class="badge badge-warning" title="Requested {{ $device->command_issued_at?->diffForHumans() }}">
                                            SYNC PENDING
                                        </span>
                                    @endif
                                </td>
                                    <td>
                                        <form action="{{ route('attendance.devices.fetch-data', $device->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-info btn-sm" {{ $device->hasPendingCommand() ? 'disabled' : '' }}>
                                                <i class="fas fa-sync"></i> Fetch Data
                                            </button>
                                        </form>

                                        <a href="{{ route('attendance.devices.edit', $device->id) }}" class="btn btn-warning btn-sm">
                                            Edit
                                        </a>

                                        @if (auth()->user()->isAdmin())
                                        <form action="{{ route('attendance.devices.destroy', $device->id) }}" method="POST" class="d-inline js-confirm-delete" data-confirm-message="Remove this device?">
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
                                <td colspan="7" class="text-center">
                                    No device yet. Add one, or just power on an F18 configured to point at this server — it will register itself automatically.
                                </td>
                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>


        <div class="card card-outline card-secondary">

            <div class="card-header">
                <h3 class="card-title">Attendance Settings</h3>
            </div>

            <form method="POST" action="{{ route('attendance.settings.update') }}">
                @csrf
                @method('PUT')

                <div class="card-body">

                    <div class="form-group">
                        <label>Weekly Off Day</label>
                        <select name="weekly_off_day" class="form-control">
                            @foreach(['Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'] as $day)
                                <option value="{{ $day }}" {{ $settings->weekly_off_day == $day ? 'selected' : '' }}>
                                    {{ $day }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">
                            Company-wide non-working day. Office start time and late grace period are now
                            configured per employee under <a href="{{ route('attendance.shifts.index') }}">Duty Shifts</a>.
                        </small>
                    </div>

                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>

            </form>

        </div>


    </div>

    <div class="col-md-5">

        <div class="card card-outline card-info">

            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle"></i> How To Connect Your ZKTeco F18
                </h3>
            </div>

            <div class="card-body">
                <p>The F18 pushes attendance data to this panel automatically (ADMS / Cloud Server mode) — no polling or extra software is needed. On the device itself, one time:</p>

                <ol class="pl-3">
                    <li>Go to <strong>Menu &rarr; COMM &rarr; Ethernet</strong> and connect it to your network.</li>
                    <li>Go to <strong>Menu &rarr; COMM &rarr; Cloud Server Setting</strong>.</li>
                    <li>Set <strong>Enable Domain Name</strong> to Off.</li>
                    <li>Set <strong>Server Address</strong> to this server's IP or domain.</li>
                    <li>Set <strong>Server Port</strong> to <code>{{ request()->getPort() ?: 80 }}</code> (this app's port).</li>
                    <li>Enable the connection and save.</li>
                </ol>

                <p class="mb-0">The device will appear in the table on the left within a minute, and its punches will start showing on the <a href="{{ route('attendance.dashboard') }}">Attendance Dashboard</a>.</p>
            </div>

        </div>

        <div class="alert alert-info">
            <i class="fas fa-sync"></i>
            <strong>Fetch Data</strong> asks a device to re-send attendance logs it already has stored on it
            (e.g. punches recorded before it was connected here). It's picked up the next time the device
            checks in, so it may take a minute — the button is disabled while a sync is pending.
        </div>

        <div class="alert alert-secondary">
            <i class="fas fa-shield-alt"></i>
            This endpoint accepts pushes from any device without a login, since the F18 itself can't authenticate — keep this server on a private network/VPN or firewalled to the device's IP.
        </div>

    </div>

</div>

@stop
