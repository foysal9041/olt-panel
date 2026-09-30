@extends('adminlte::page')

@section('title', 'Users')

@php
    $isAdmin = strtolower(auth()->user()->role) === 'admin';
    $roleStyle = [
        'admin' => ['Admin', 'badge-danger'],
        'noc' => ['NOC', 'badge-info'],
        'operator' => ['Operator', 'badge-primary'],
        'employee' => ['Employee', 'badge-secondary'],
        'viewer' => ['Viewer', 'badge-warning'],
    ];
    $initials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('');
@endphp

@section('content_header')
<x-settings.header title="Users" icon="fas fa-users" subtitle="Who can sign in, their role, zone and which modules they can open">
    @if ($isAdmin)
        <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-user-plus"></i> Add User</a>
    @endif
</x-settings.header>
@stop

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="acct-stats">
    <div class="acct-stat" style="--accent:#4f46e5">
        <div class="acct-stat-label">Users <i class="fas fa-users"></i></div>
        <div class="acct-stat-value">{{ $users->count() }}</div>
        <div class="acct-stat-foot">{{ $users->where('status', true)->count() }} can sign in</div>
    </div>
    <div class="acct-stat" style="--accent:#e11d48">
        <div class="acct-stat-label">Admins <i class="fas fa-user-shield"></i></div>
        <div class="acct-stat-value">{{ $users->filter(fn ($u) => strtolower($u->role) === 'admin')->count() }}</div>
        <div class="acct-stat-foot">Full access to everything</div>
    </div>
    <div class="acct-stat" style="--accent:#d97706">
        <div class="acct-stat-label">View only <i class="fas fa-eye"></i></div>
        <div class="acct-stat-value">{{ $users->filter(fn ($u) => strtolower($u->role) === 'viewer')->count() }}</div>
        <div class="acct-stat-foot">Can look, can't change</div>
    </div>
    <div class="acct-stat" style="--accent:#64748b">
        <div class="acct-stat-label">Disabled <i class="fas fa-user-slash"></i></div>
        <div class="acct-stat-value">{{ $users->where('status', false)->count() }}</div>
        <div class="acct-stat-foot">Can't sign in</div>
    </div>
</div>

<div class="card acct-panel">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-id-badge mr-1 text-primary"></i> All users</h3>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover data-table mb-0">
                <thead>
                    <tr>
                        <th class="pl-3" style="width:3rem">SL</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Zone</th>
                        <th>Modules</th>
                        <th>Last active</th>
                        <th>Status</th>
                        <th class="text-right pr-3" data-orderable="false">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        @php [$roleLabel, $roleClass] = $roleStyle[strtolower($user->role)] ?? [ucfirst($user->role), 'badge-secondary']; @endphp
                        <tr>
                            <td class="pl-3 text-muted">{{ $loop->iteration }}</td>
                            <td>
                                <div class="d-flex align-items-center" style="gap:.65rem">
                                    <span class="acct-avatar">{{ $initials($user->name) ?: '?' }}</span>
                                    <div style="min-width:0">
                                        <a href="{{ route('users.show', $user) }}" class="font-weight-bold" style="color:#0f172a">{{ $user->name }}</a>
                                        @if ($user->id === auth()->id()) <span class="badge badge-light border ml-1">You</span> @endif
                                        <div class="small text-muted">{{ $user->username }}{{ $user->email ? ' · ' . $user->email : '' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge {{ $roleClass }}">{{ $roleLabel }}</span></td>
                            <td class="small">{{ $user->zone && $user->zone !== 'all' ? $user->zone : 'All zones' }}</td>
                            <td style="max-width: 22rem">
                                @if (strtolower($user->role) === 'admin')
                                    <span class="badge badge-dark">Everything</span>
                                @elseif ($user->modulePermissions->isEmpty())
                                    <span class="text-muted small">None</span>
                                @else
                                    @foreach ($user->modulePermissions->groupBy('module') as $moduleKey => $grants)
                                        @php
                                            $moduleLabel = config("modules.{$moduleKey}.label", $moduleKey);
                                            $isFull = $grants->contains('submodule', '');
                                        @endphp
                                        @if ($isFull)
                                            <span class="badge badge-info mb-1">{{ $moduleLabel }}</span>
                                        @else
                                            <span class="badge badge-secondary mb-1"
                                                  title="{{ $grants->map(fn ($g) => config("modules.{$moduleKey}.submodules.{$g->submodule}", $g->submodule))->implode(', ') }}">
                                                {{ $moduleLabel }} · {{ $grants->count() }}
                                            </span>
                                        @endif
                                    @endforeach
                                @endif
                            </td>
                            <td class="small" data-order="{{ $user->last_active_at?->timestamp ?? 0 }}">
                                @if ($user->last_active_at)
                                    <span title="{{ $user->last_active_at->format('d M Y, h:i A') }}">{{ $user->last_active_at->diffForHumans() }}</span>
                                    @if ($user->last_login_at)
                                        <div class="text-muted">signed in {{ $user->last_login_at->diffForHumans() }}</div>
                                    @endif
                                @else
                                    <span class="text-muted">Never</span>
                                @endif
                            </td>
                            <td>
                                @if ($user->status)
                                    <span class="badge badge-success"><i class="fas fa-circle" style="font-size:.45rem;vertical-align:middle"></i> Active</span>
                                @else
                                    <span class="badge badge-danger">Disabled</span>
                                @endif
                            </td>
                            <td class="text-right pr-3 text-nowrap">
                                <a href="{{ route('activity.index', ['user' => $user->id]) }}" class="btn btn-light btn-sm" title="Activity log"><i class="fas fa-history"></i></a>
                                @if ($isAdmin)
                                    <a href="{{ route('users.edit', $user) }}" class="btn btn-light btn-sm" title="Edit"><i class="fas fa-pen text-primary"></i></a>
                                @endif
                                @if ($user->id !== auth()->id())
                                    <form action="{{ route('users.destroy', $user) }}" method="POST" class="d-inline js-confirm-delete"
                                          data-confirm-message="Delete user {{ $user->name }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-light btn-sm" title="Delete"><i class="fas fa-trash text-danger"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@stop
