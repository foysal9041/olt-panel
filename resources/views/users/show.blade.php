@extends('adminlte::page')

@section('title','User Activity')

@section('content')

<div class="card">

<div class="card-header d-flex justify-content-between align-items-center">

    <h3 class="card-title">
        {{ $user->name }}'s Activity Log
    </h3>

    <a href="{{ route('users.index') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i>
        Back to Users
    </a>

</div>

<div class="card-body">

    <dl class="row">
        <dt class="col-sm-2">Username</dt>
        <dd class="col-sm-4">{{ $user->username }}</dd>

        <dt class="col-sm-2">Role</dt>
        <dd class="col-sm-4">{{ ucfirst($user->role) }}</dd>

        <dt class="col-sm-2">Email</dt>
        <dd class="col-sm-4">{{ $user->email }}</dd>

        <dt class="col-sm-2">Zone</dt>
        <dd class="col-sm-4">{{ $user->zone ?? 'All Zones' }}</dd>
    </dl>

    <form method="GET" action="{{ route('users.show', $user->id) }}" class="form-inline mb-3">

        <label class="mr-2">Action</label>
        <select name="action" class="form-control mr-3">
            <option value="">All</option>
            @foreach(['login' => 'Login', 'logout' => 'Logout', 'created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted'] as $value => $label)
                <option value="{{ $value }}" {{ request('action') == $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>

        <label class="mr-2">From</label>
        <input type="date" name="from" value="{{ request('from') }}" class="form-control mr-3">

        <label class="mr-2">To</label>
        <input type="date" name="to" value="{{ request('to') }}" class="form-control mr-3">

        <button type="submit" class="btn btn-primary">Filter</button>

        @if(request()->hasAny(['action', 'from', 'to']))
            <a href="{{ route('users.show', $user->id) }}" class="btn btn-link">Clear</a>
        @endif

    </form>

    <table class="table table-bordered table-striped">

        <thead>
        <tr>
            <th width="150">When</th>
            <th width="100">Action</th>
            <th>Description</th>
            <th width="180">IP Address</th>
        </tr>
        </thead>

        <tbody>

        @forelse($activity as $log)
        <tr>
            <td>{{ $log->created_at->format('d M Y, h:i A') }}</td>

            <td>
                @php
                    $badge = match($log->action) {
                        'login' => 'success',
                        'logout' => 'secondary',
                        'created' => 'info',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'light',
                    };
                @endphp
                <span class="badge badge-{{ $badge }}">{{ ucfirst($log->action) }}</span>
            </td>

            <td>
                {{ $log->description }}

                @if(!empty($log->changes))
                    <ul class="mb-0 mt-1 small text-muted">
                        @foreach($log->changes as $field => $change)
                            <li>
                                <strong>{{ \Illuminate\Support\Str::headline($field) }}:</strong>
                                {{ $change['old'] ?? '—' }} &rarr; {{ $change['new'] ?? '—' }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </td>

            <td>{{ $log->ip_address }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="4" class="text-center text-muted">No activity recorded yet.</td>
        </tr>
        @endforelse

        </tbody>

    </table>

    {{ $activity->links() }}

</div>

</div>

@stop
