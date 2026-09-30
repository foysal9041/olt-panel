@extends('adminlte::page')

@section('title', $user->name . ' — Activity')

@php
    $initials = collect(preg_split('/\s+/', trim((string) $user->name)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('');
    $actionStyle = [
        'login' => ['badge-success', 'fas fa-sign-in-alt'],
        'logout' => ['badge-secondary', 'fas fa-sign-out-alt'],
        'created' => ['badge-info', 'fas fa-plus'],
        'updated' => ['badge-warning', 'fas fa-pen'],
        'deleted' => ['badge-danger', 'fas fa-trash'],
    ];
@endphp

@section('content_header')
<x-settings.header :title="$user->name" subtitle="Account details and activity log" :back="route('users.index')">
    @if (strtolower(auth()->user()->role) === 'admin')
        <a href="{{ route('users.edit', $user) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-pen"></i> Edit user</a>
    @endif
</x-settings.header>
@stop

@section('content')

<div class="row">
    <div class="col-lg-4">
        <div class="card acct-panel">
            <div class="card-body text-center pt-4">
                <span class="acct-avatar mx-auto mb-2" style="width:4rem;height:4rem;font-size:1.3rem">{{ $initials ?: '?' }}</span>
                <h5 class="mb-0 font-weight-bold">{{ $user->name }}</h5>
                <div class="text-muted small mb-2">{{ $user->username }}</div>
                <span class="badge badge-primary">{{ ucfirst($user->role) }}</span>
                @if ($user->status)
                    <span class="badge badge-success">Active</span>
                @else
                    <span class="badge badge-danger">Disabled</span>
                @endif
            </div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Email</span><span>{{ $user->email ?: '—' }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Zone</span><span>{{ $user->zone && $user->zone !== 'all' ? $user->zone : 'All zones' }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Joined</span><span>{{ $user->created_at?->format('d M Y') ?? '—' }}</span></li>
            </ul>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card acct-panel">
            <div class="card-header flex-wrap" style="gap:.5rem">
                <h3 class="card-title mr-auto"><i class="fas fa-history mr-1 text-primary"></i> Activity</h3>
                <form method="GET" action="{{ route('users.show', $user) }}" class="form-inline" style="gap:.35rem">
                    <select name="action" class="form-control form-control-sm">
                        <option value="">All actions</option>
                        @foreach (['login' => 'Login', 'logout' => 'Logout', 'created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('action') == $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm" title="From">
                    <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm" title="To">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
                    @if (request()->hasAny(['action', 'from', 'to']))
                        <a href="{{ route('users.show', $user) }}" class="btn btn-link btn-sm">Clear</a>
                    @endif
                </form>
            </div>
            <div class="card-body p-0">
                @forelse ($activity as $log)
                    @php [$badge, $icon] = $actionStyle[$log->action] ?? ['badge-light', 'fas fa-circle']; @endphp
                    <div class="acct-list-row align-items-start">
                        <span class="badge {{ $badge }} mt-1" style="min-width:5.5rem"><i class="{{ $icon }} mr-1"></i>{{ ucfirst($log->action) }}</span>
                        <div class="acct-list-main">
                            <div style="color:#0f172a">{{ $log->description }}</div>
                            @if (! empty($log->changes))
                                <ul class="mb-0 mt-1 pl-3 small text-muted">
                                    @foreach ($log->changes as $field => $change)
                                        <li><strong>{{ \Illuminate\Support\Str::headline($field) }}:</strong> {{ $change['old'] ?? '—' }} &rarr; {{ $change['new'] ?? '—' }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            <div class="acct-list-sub mt-1">
                                <i class="far fa-clock"></i> {{ $log->created_at->format('d M Y, h:i A') }} · {{ $log->created_at->diffForHumans() }}
                                @if ($log->ip_address) · <i class="fas fa-globe"></i> {{ $log->ip_address }} @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="acct-empty"><i class="fas fa-history"></i>No activity recorded yet.</div>
                @endforelse
            </div>
            @if ($activity->hasPages())
                <div class="card-footer">{{ $activity->withQueryString()->links() }}</div>
            @endif
        </div>
    </div>
</div>

@stop
