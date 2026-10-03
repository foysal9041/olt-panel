@extends('adminlte::page')

@section('title', 'Tickets')

@use('App\Models\Ticket')
@php
    $manage = auth()->user()->can('access-tickets-manage');
    $tabs = array_filter([
        'mine' => ['Given to me', 'fas fa-user-check'],
        'active' => $manage ? ['All open', 'fas fa-folder-open'] : null,
        'overdue' => ['Overdue', 'fas fa-fire'],
        'unassigned' => $manage ? ['Not assigned', 'fas fa-user-slash'] : null,
        'done' => ['Resolved / closed', 'fas fa-check-circle'],
        'all' => ['All', 'fas fa-list'],
    ]);
@endphp

@section('content_header')
<x-work.header title="Tickets" icon="fas fa-ticket-alt" subtitle="Customer and network problems — who's on it and where it stands">
    @can('access-tickets-manage')
        <a href="{{ route('tickets.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New ticket</a>
    @endcan
</x-work.header>
@stop

@section('content')

@include('inventory.partials.alerts')

<div class="acct-stats">
    <a href="{{ route('tickets.index', ['view' => 'mine']) }}" class="acct-stat text-reset" style="--accent:#4f46e5">
        <div class="acct-stat-label">Given to me <i class="fas fa-user-check"></i></div>
        <div class="acct-stat-value">{{ $counts['mine'] }}</div>
        <div class="acct-stat-foot">Open tickets you're working on</div>
    </a>
    <a href="{{ route('tickets.index', ['view' => 'active']) }}" class="acct-stat text-reset" style="--accent:#d97706">
        <div class="acct-stat-label">Open <i class="fas fa-folder-open"></i></div>
        <div class="acct-stat-value">{{ $counts['active'] }}</div>
        <div class="acct-stat-foot">{{ $counts['unassigned'] }} not assigned yet</div>
    </a>
    <a href="{{ route('tickets.index', ['view' => 'overdue']) }}" class="acct-stat text-reset" style="--accent:{{ $counts['overdue'] ? '#dc2626' : '#64748b' }}">
        <div class="acct-stat-label">Overdue <i class="fas fa-fire"></i></div>
        <div class="acct-stat-value {{ $counts['overdue'] ? 'text-danger' : '' }}">{{ $counts['overdue'] }}</div>
        <div class="acct-stat-foot">Past the time to fix</div>
    </a>
    <a href="{{ route('tickets.index', ['view' => 'done']) }}" class="acct-stat text-reset" style="--accent:#16a34a">
        <div class="acct-stat-label">Resolved today <i class="fas fa-check-circle"></i></div>
        <div class="acct-stat-value">{{ $counts['resolved_today'] }}</div>
        <div class="acct-stat-foot">{{ $counts['done'] }} resolved / closed in all</div>
    </a>
</div>

<div class="wk-tabs">
    @foreach ($tabs as $key => [$tLabel, $tIcon])
        <a href="{{ route('tickets.index', ['view' => $key]) }}" @class(['active' => $view === $key, 'danger' => $key === 'overdue'])>
            <i class="{{ $tIcon }}"></i> {{ \App\Support\Ui::t($tLabel) }}
            @isset($counts[$key])<b>{{ $counts[$key] }}</b>@endisset
        </a>
    @endforeach
</div>

<div class="card acct-panel">
    <div class="card-header flex-wrap" style="gap:.5rem">
        <form method="GET" class="form-inline flex-wrap" style="gap:.4rem">
            <input type="hidden" name="view" value="{{ $view }}">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search no, subject, customer, phone">
            <select name="category" class="form-control form-control-sm">
                <option value="">Any problem</option>
                @foreach (Ticket::CATEGORIES as $k => [$cLabel])
                    <option value="{{ $k }}" @selected(request('category') === $k)>{{ \App\Support\Ui::t($cLabel) }}</option>
                @endforeach
            </select>
            <select name="priority" class="form-control form-control-sm">
                <option value="">Any priority</option>
                @foreach (Ticket::PRIORITIES as $k => [$pLabel])
                    <option value="{{ $k }}" @selected(request('priority') === $k)>{{ \App\Support\Ui::t($pLabel) }}</option>
                @endforeach
            </select>
            @if ($manage)
                <select name="assigned" class="form-control form-control-sm">
                    <option value="">Anyone</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected(request('assigned') == $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            @endif
            <select name="zone" class="form-control form-control-sm" style="max-width: 11rem">
                <option value="">Any zone</option>
                @foreach ($zones as $z)
                    <option value="{{ $z }}" @selected(request('zone') === $z)>{{ $z }}</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-light border"><i class="fas fa-filter"></i></button>
            @if (request()->hasAny(['q', 'category', 'priority', 'assigned', 'zone', 'status']))
                <a href="{{ route('tickets.index', ['view' => $view]) }}" class="btn btn-sm btn-link">Clear</a>
            @endif
        </form>
        <span class="small text-muted">{{ $tickets->total() }} {{ \App\Support\Ui::t(Str::plural('ticket', $tickets->total())) }}</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table wk-table">
                <thead>
                    <tr>
                        <th class="pl-3">Ticket</th>
                        <th>Customer / place</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Given to</th>
                        <th class="pr-3">Fix by</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $t)
                        <tr @class(['late' => $t->isOverdue()])>
                            <td class="pl-3">
                                <div class="d-flex align-items-center" style="gap:.65rem">
                                    <span class="wk-cat" title="{{ \App\Support\Ui::t($t->categoryLabel()) }}"><i class="{{ $t->categoryIcon() }}"></i></span>
                                    <div style="min-width:0">
                                        <span class="wk-no">{{ $t->number() }}</span>
                                        <a href="{{ route('tickets.show', $t) }}" class="wk-subject d-block text-truncate" style="max-width: 22rem">{{ $t->subject }}</a>
                                        <div class="inv-sub">{{ $t->categoryLabel() }} · {{ $t->created_at->diffForHumans() }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="small">
                                <div class="font-weight-bold">{{ $t->customer?->name ?? $t->contact_name ?? '—' }}</div>
                                <div class="text-muted">{{ collect([$t->zone !== $t->customer?->name ? $t->zone : null, $t->contact_phone])->filter()->implode(' · ') }}</div>
                            </td>
                            <td><span class="wk-pri" style="--c: {{ $t->priorityColor() }}">{{ $t->priorityLabel() }}</span></td>
                            <td><span class="wk-pill" style="--c: {{ $t->statusColor() }}">{{ $t->statusLabel() }}</span></td>
                            <td class="small text-nowrap">
                                @if ($t->assignee)
                                    <span class="wk-av sm mr-1">{{ $t->assignee->initials() }}</span>{{ $t->assignee->name }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="pr-3 small text-nowrap {{ $t->isOverdue() ? 'text-danger font-weight-bold' : 'text-muted' }}">
                                @if ($t->isActive() && $t->due_at)
                                    {{ $t->due_at->format('d M, h:i A') }}
                                    <div>{{ $t->isOverdue() ? \App\Support\Ui::t('late') . ' ' . $t->due_at->diffForHumans(null, true) : $t->due_at->diffForHumans() }}</div>
                                @elseif ($t->resolved_at)
                                    <i class="fas fa-check text-success"></i> {{ $t->resolved_at->format('d M, h:i A') }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="acct-empty"><i class="fas fa-ticket-alt"></i>
                            @if ($view === 'mine') No open tickets given to you. @else No tickets here. @endif
                        </div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($tickets->hasPages())
        <div class="card-footer">{{ $tickets->links() }}</div>
    @endif
</div>

@stop
