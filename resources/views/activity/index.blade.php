@extends('adminlte::page')

@section('title', $mine ? 'My Activity' : 'Activity Log')

@php
    $initials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') ?: '?';
    $filtered = collect($filters)->filter()->isNotEmpty();
    $day = fn ($date) => $date->isToday() ? 'Today' : ($date->isYesterday() ? 'Yesterday' : $date->format('l, d M Y'));
    $lastDay = null;
@endphp

@section('content_header')
<x-settings.header :title="$mine ? 'My Activity' : 'Activity Log'" icon="fas fa-history"
    :subtitle="$mine ? 'Everything you have done in the panel — sign-ins and changes' : 'Who did what and when — sign-ins, changes and access updates for every user'" />
@stop

@section('css')
<style>
    .act-filter label { font-size: .7rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #64748b; margin-bottom: .2rem; }
    .act-day { position: sticky; top: 0; z-index: 1; padding: .45rem 1.15rem; background: #f8fafc; border-bottom: 1px solid #eef2f7;
        font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #64748b; }
    .act-row { display: flex; align-items: flex-start; gap: .8rem; padding: .75rem 1.15rem; border-bottom: 1px solid #f1f5f9; }
    .act-row:hover { background: #fafbff; }
    .act-main { flex: 1; min-width: 0; }
    .act-who { font-weight: 600; color: #0f172a; }
    .act-desc { color: #334155; }
    .act-meta { margin-top: .2rem; font-size: .76rem; color: #94a3b8; }
    .act-meta span + span::before { content: '·'; margin: 0 .4rem; color: #cbd5e1; }
    .act-changes { margin: .35rem 0 0; padding: .45rem .7rem; list-style: none; border-radius: .5rem; background: #f8fafc; font-size: .78rem; color: #475569; }
    .act-changes li + li { margin-top: .15rem; }
    .act-changes del { color: #be123c; text-decoration-color: rgba(190, 18, 60, .4); }
    .act-changes ins { color: #15803d; text-decoration: none; }
    .act-badge { min-width: 7.2rem; text-align: left; }
    .act-time { flex: none; font-size: .78rem; color: #64748b; white-space: nowrap; font-variant-numeric: tabular-nums; }
</style>
@stop

@section('content')

<div class="acct-stats">
    <div class="acct-stat" style="--accent:#4f46e5">
        <div class="acct-stat-label">Today <i class="fas fa-history"></i></div>
        <div class="acct-stat-value">{{ number_format($stats['today']) }}</div>
        <div class="acct-stat-foot">actions recorded</div>
    </div>
    @unless ($mine)
        <div class="acct-stat" style="--accent:#16a34a">
            <div class="acct-stat-label">Active today <i class="fas fa-users"></i></div>
            <div class="acct-stat-value">{{ $stats['people'] }}</div>
            <div class="acct-stat-foot">people used the panel</div>
        </div>
    @endunless
    <div class="acct-stat" style="--accent:#d97706">
        <div class="acct-stat-label">Changes today <i class="fas fa-pen"></i></div>
        <div class="acct-stat-value">{{ number_format($stats['changes']) }}</div>
        <div class="acct-stat-foot">created, updated, deleted</div>
    </div>
    <a href="{{ request()->fullUrlWithQuery(['action' => 'login_failed', 'page' => null]) }}" class="acct-stat" style="--accent:#e11d48; color:inherit; text-decoration:none">
        <div class="acct-stat-label">Failed sign-ins (24h) <i class="fas fa-user-lock"></i></div>
        <div class="acct-stat-value {{ $stats['failed'] ? 'text-danger' : '' }}">{{ $stats['failed'] }}</div>
        <div class="acct-stat-foot">{{ $stats['failed'] ? 'Show them' : 'None' }}</div>
    </a>
</div>

<div class="card acct-panel">
    <div class="card-body pb-1 act-filter">
        <form method="GET" class="form-row align-items-end">
            @unless ($mine)
                <div class="col-6 col-md-3 col-lg-2 form-group">
                    <label>User</label>
                    <select name="user" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">Everyone</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected($filters['user'] === $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless
            <div class="col-6 col-md-3 col-lg-2 form-group">
                <label>Action</label>
                <select name="action" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value="">All actions</option>
                    @foreach (\App\Models\ActivityLog::ACTIONS as $key => [$label])
                        <option value="{{ $key }}" @selected($filters['action'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2 form-group">
                <label>What</label>
                <select name="subject" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value="">Anything</option>
                    @foreach ($subjects as $s)
                        <option value="{{ $s }}" @selected($filters['subject'] === $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2 form-group">
                <label>From</label>
                <input type="date" name="from" value="{{ $filters['from'] }}" class="form-control form-control-sm" onchange="this.form.submit()">
            </div>
            <div class="col-6 col-md-3 col-lg-1 form-group">
                <label>To</label>
                <input type="date" name="to" value="{{ $filters['to'] }}" class="form-control form-control-sm" onchange="this.form.submit()">
            </div>
            <div class="col-6 col-md-6 col-lg-2 form-group">
                <label>Search</label>
                <input type="search" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="OLT name, IP, amount…">
            </div>
            <div class="col-12 col-md-3 col-lg-1 form-group d-flex" style="gap:.3rem">
                <button class="btn btn-primary btn-sm flex-fill" title="Filter"><i class="fas fa-filter"></i></button>
                @if ($filtered)
                    <a href="{{ url()->current() }}" class="btn btn-light btn-sm" title="Clear"><i class="fas fa-times"></i></a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card acct-panel">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-stream mr-1 text-primary"></i> {{ $mine ? 'Your activity' : 'All activity' }}</h3>
        <span class="small text-muted">{{ number_format($logs->total()) }} {{ Str::plural('entry', $logs->total()) }}</span>
    </div>
    <div class="card-body p-0">
        @forelse ($logs as $log)
            @php [$label, $badge, $icon] = $log->style(); @endphp
            @if ($lastDay !== $log->created_at->toDateString())
                @php $lastDay = $log->created_at->toDateString(); @endphp
                <div class="act-day">{{ $day($log->created_at) }}</div>
            @endif
            <div class="act-row">
                @unless ($mine)
                    <span class="acct-avatar" title="{{ $log->user?->name ?? 'Unknown' }}">{{ $initials($log->user?->name) }}</span>
                @endunless
                <div class="act-main">
                    <div>
                        <span class="badge {{ $badge }} act-badge mr-1"><i class="{{ $icon }} mr-1"></i>{{ $label }}</span>
                        @unless ($mine)
                            @if ($log->user)
                                <a href="{{ request()->fullUrlWithQuery(['user' => $log->user_id, 'page' => null]) }}" class="act-who">{{ $log->user->name }}</a>
                            @else
                                <span class="act-who text-muted">Unknown user</span>
                            @endif
                            <span class="text-muted">—</span>
                        @endunless
                        <span class="act-desc">{{ $log->description }}</span>
                    </div>
                    @if (! empty($log->changes))
                        <ul class="act-changes">
                            @foreach ($log->changes as $field => $change)
                                <li>
                                    <strong>{{ \Illuminate\Support\Str::headline($field) }}:</strong>
                                    <del>{{ is_scalar($change['old'] ?? null) ? \Illuminate\Support\Str::limit((string) $change['old'], 120) : '—' }}</del>
                                    &rarr;
                                    <ins>{{ is_scalar($change['new'] ?? null) ? \Illuminate\Support\Str::limit((string) $change['new'], 120) : '—' }}</ins>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="act-meta">
                        @if ($log->ip_address)<span><i class="fas fa-globe"></i> {{ $log->ip_address }}</span>@endif
                        @if ($d = $log->device())<span><i class="fas fa-desktop"></i> {{ $d }}</span>@endif
                    </div>
                </div>
                <div class="act-time" title="{{ $log->created_at->format('d M Y, h:i:s A') }}">
                    {{ $log->created_at->format('h:i A') }}
                    <div class="small text-muted text-right">{{ $log->created_at->diffForHumans(null, true) }} ago</div>
                </div>
            </div>
        @empty
            <div class="acct-empty"><i class="fas fa-history"></i>{{ $filtered ? 'Nothing matches these filters.' : 'No activity recorded yet.' }}</div>
        @endforelse
    </div>
    @if ($logs->hasPages())
        <div class="card-footer">{{ $logs->links() }}</div>
    @endif
</div>

@stop
