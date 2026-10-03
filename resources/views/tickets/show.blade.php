@extends('adminlte::page')

@use('App\Models\Ticket')
@php
    $user = auth()->user();
    $manage = $user->can('access-tickets-manage');
@endphp

@section('title', $ticket->number())

@section('content_header')
<x-work.header :title="$ticket->number() . ' · ' . $ticket->subject" icon="fas fa-ticket-alt" :back="route('tickets.index')"
    :subtitle="$ticket->categoryLabel() . ' · ' . \App\Support\Ui::t('opened') . ' ' . $ticket->created_at->format('d M Y, h:i A') . ($ticket->creator ? ' · ' . $ticket->creator->name : '')">
    @if ($manage)
        <a href="{{ route('tickets.edit', $ticket) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-pen"></i> Edit</a>
    @endif
</x-work.header>
@stop

@section('content')

@include('inventory.partials.alerts')

<div class="row">
    <div class="col-lg-8">
        @if ($ticket->description)
            <div class="card acct-panel">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-align-left mr-1 text-primary"></i> Details</h3></div>
                <div class="card-body" style="white-space: pre-line">{{ $ticket->description }}</div>
            </div>
        @endif

        {{-- Work on it --}}
        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-reply mr-1 text-primary"></i> Update</h3></div>
            <form method="POST" action="{{ route('tickets.progress', $ticket) }}" class="card-body viewer-ok">
                @csrf
                <div class="form-group">
                    <label>Status</label>
                    <div class="wk-status-pick">
                        @foreach (Ticket::STATUSES as $k => [$sLabel, $sColor, $sIcon])
                            @continue($k === 'closed' && ! $manage)
                            <label style="--c: {{ $sColor }}">
                                <input type="radio" name="status" value="{{ $k }}" @checked($ticket->status === $k)>
                                <span><i class="{{ $sIcon }}"></i> {{ \App\Support\Ui::t($sLabel) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                @if ($manage)
                    <div class="form-group">
                        <label>Given to</label>
                        <select name="assigned_to" class="form-control">
                            <option value="">— nobody —</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected($ticket->assigned_to === $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="form-group">
                    <label>Note</label>
                    <textarea name="body" rows="3" class="form-control" maxlength="5000" placeholder="What was found / done — e.g. Fiber cut at Bagachara bridge, splicing team sent"></textarea>
                </div>
                <button class="btn btn-primary"><i class="fas fa-paper-plane"></i> Save update</button>
            </form>
        </div>

        {{-- Timeline --}}
        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-stream mr-1 text-primary"></i> Timeline</h3></div>
            <div class="card-body">
                <ul class="wk-timeline">
                    @foreach ($ticket->updates as $u)
                        <li>
                            <span class="wk-av">{{ $u->user?->initials() ?? '?' }}</span>
                            <div class="wk-tl-body">
                                <div class="wk-tl-head"><b>{{ $u->user?->name ?? 'Someone' }}</b> · {{ $u->created_at->format('d M Y, h:i A') }} <span class="text-muted">({{ $u->created_at->diffForHumans() }})</span></div>
                                @include('tickets.partials.changes', ['changes' => $u->changes ?? [], 'statuses' => Ticket::STATUSES])
                                @if ($u->body)<div class="wk-tl-note">{{ $u->body }}</div>@endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card acct-panel">
            <div class="card-body">
                <dl class="wk-facts">
                    <div><dt>Status</dt><dd><span class="wk-pill" style="--c: {{ $ticket->statusColor() }}">{{ $ticket->statusLabel() }}</span></dd></div>
                    <div><dt>Priority</dt><dd><span class="wk-pri" style="--c: {{ $ticket->priorityColor() }}">{{ $ticket->priorityLabel() }}</span></dd></div>
                    <div><dt>Given to</dt><dd>@if ($ticket->assignee)<span class="wk-av sm mr-1">{{ $ticket->assignee->initials() }}</span>{{ $ticket->assignee->name }}@else <span class="text-danger">Not assigned</span> @endif</dd></div>
                    <div><dt>Fix by</dt><dd class="{{ $ticket->isOverdue() ? 'text-danger font-weight-bold' : '' }}">
                        {{ $ticket->due_at?->format('d M Y, h:i A') ?? '—' }}
                        @if ($ticket->isOverdue())<div class="small"><i class="fas fa-fire"></i> {{ \App\Support\Ui::t('late') }} {{ $ticket->due_at->diffForHumans(null, true) }}</div>@endif
                    </dd></div>
                    @if ($ticket->resolved_at)<div><dt>Resolved</dt><dd>{{ $ticket->resolved_at->format('d M Y, h:i A') }}<div class="small text-muted">{{ $ticket->created_at->diffForHumans($ticket->resolved_at, true) }} {{ \App\Support\Ui::t('after opening') }}</div></dd></div>@endif
                    <div><dt>Customer</dt><dd>{{ $ticket->customer?->name ?? '—' }}</dd></div>
                    <div><dt>Zone</dt><dd>{{ $ticket->zone ?? '—' }}</dd></div>
                    <div><dt>Contact</dt><dd>{{ $ticket->contact_name ?? '—' }}@if ($ticket->contact_phone)<div><a href="tel:{{ $ticket->contact_phone }}"><i class="fas fa-phone-alt"></i> {{ $ticket->contact_phone }}</a></div>@endif</dd></div>
                    @if ($ticket->source)<div><dt>Came by</dt><dd>{{ \App\Support\Ui::t($ticket->sourceLabel()) }}</dd></div>@endif
                </dl>
            </div>
        </div>

        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-tasks mr-1 text-primary"></i> Tasks</h3></div>
            <div class="card-body p-0">
                @foreach ($ticket->tasks as $task)
                    <a href="{{ route('my.tasks.show', $task) }}" class="acct-list-row">
                        <span class="wk-av sm">{{ $task->assignee?->initials() }}</span>
                        <div class="acct-list-main">
                            <div class="acct-list-title">{{ $task->title }}</div>
                            <div class="acct-list-sub">{{ $task->assignee?->name }} · {{ $task->progress }}%</div>
                        </div>
                        <span class="wk-pill" style="--c: {{ $task->statusColor() }}">{{ $task->statusLabel() }}</span>
                    </a>
                @endforeach
                <form method="POST" action="{{ route('tickets.task', $ticket) }}" class="p-3 viewer-ok {{ $ticket->tasks->isNotEmpty() ? 'border-top' : '' }}">
                    @csrf
                    <input type="text" name="title" class="form-control form-control-sm mb-2" placeholder="New task, e.g. Replace ONU at customer" maxlength="255" required>
                    <div class="d-flex" style="gap:.4rem">
                        <select name="assigned_to" class="form-control form-control-sm">
                            @foreach ($user->can('access-tasks-assign') ? $users : collect([$user]) as $u)
                                <option value="{{ $u->id }}" @selected(($ticket->assigned_to ?? $user->id) === $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                        <input type="date" name="due_date" class="form-control form-control-sm" style="max-width: 9.5rem">
                        <button class="btn btn-sm btn-primary"><i class="fas fa-plus"></i></button>
                    </div>
                </form>
            </div>
        </div>

        @if ($history->isNotEmpty())
            <div class="card acct-panel">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-history mr-1 text-primary"></i> Earlier tickets of {{ $ticket->customer->name }}</h3></div>
                <div class="card-body p-0">
                    @foreach ($history as $h)
                        <a href="{{ route('tickets.show', $h) }}" class="acct-list-row">
                            <div class="acct-list-main">
                                <div class="acct-list-title">{{ $h->subject }}</div>
                                <div class="acct-list-sub">{{ $h->number() }} · {{ $h->created_at->format('d M Y') }}</div>
                            </div>
                            <span class="wk-pill" style="--c: {{ $h->statusColor() }}">{{ $h->statusLabel() }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($user->isAdmin())
            <form method="POST" action="{{ route('tickets.destroy', $ticket) }}" class="js-confirm-delete text-center" data-confirm-message="Remove this ticket and its timeline?">
                @csrf @method('DELETE')
                <button class="btn btn-link btn-sm text-danger"><i class="fas fa-trash"></i> Remove ticket</button>
            </form>
        @endif
    </div>
</div>

@stop
