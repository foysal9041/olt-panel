@extends('adminlte::page')

@use('App\Models\Task')
@php
    $user = auth()->user();
    $doing = $task->assigned_to === $user->id;
    $gave = $task->created_by === $user->id;
    $canWork = $doing || $gave || $user->isAdmin();
    $canEdit = $gave || $user->isAdmin();
@endphp

@section('title', $task->title)

@section('content_header')
<x-work.header :title="$task->title" icon="fas fa-check-square" :back="route('my.tasks.index', ['tab' => $doing ? 'mine' : 'given'])"
    :subtitle="$task->isPersonal() ? \App\Support\Ui::t('Your to-do') : ($task->creator?->name ?? '—') . ' → ' . $task->assignee?->name" />
@stop

@section('content')

@include('inventory.partials.alerts')

<div class="row">
    <div class="col-lg-8">
        @if ($task->description)
            <div class="card acct-panel">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-align-left mr-1 text-primary"></i> Details</h3></div>
                <div class="card-body" style="white-space: pre-line">{{ $task->description }}</div>
            </div>
        @endif

        @if ($canWork)
            <div class="card acct-panel">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-tasks mr-1 text-primary"></i> Where is it now?</h3></div>
                <form method="POST" action="{{ route('my.tasks.progress', $task) }}" class="card-body viewer-ok">
                    @csrf
                    <div class="form-group">
                        <div class="wk-status-pick">
                            @foreach (Task::STATUSES as $k => [$sLabel, $sColor, $sIcon])
                                <label style="--c: {{ $sColor }}">
                                    <input type="radio" name="status" value="{{ $k }}" @checked($task->status === $k)>
                                    <span><i class="{{ $sIcon }}"></i> {{ \App\Support\Ui::t($sLabel) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Progress: <b id="tk-pct">{{ $task->progress }}%</b></label>
                        <input type="range" name="progress" min="0" max="100" step="5" value="{{ $task->progress }}" class="custom-range"
                               oninput="document.getElementById('tk-pct').textContent = this.value + '%'">
                    </div>
                    <div class="form-group">
                        <label>Note</label>
                        <textarea name="body" rows="3" class="form-control" maxlength="5000" placeholder="{{ \App\Support\Ui::t("What's done, what's left, anything blocking it") }}"></textarea>
                    </div>
                    <button class="btn btn-primary"><i class="fas fa-paper-plane"></i> Save update</button>
                </form>
            </div>
        @endif

        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-stream mr-1 text-primary"></i> Updates</h3></div>
            <div class="card-body">
                @if ($task->updates->isEmpty())
                    <div class="text-muted small">No updates yet.</div>
                @else
                    <ul class="wk-timeline">
                        @foreach ($task->updates as $u)
                            <li>
                                <span class="wk-av">{{ $u->user?->initials() ?? '?' }}</span>
                                <div class="wk-tl-body">
                                    <div class="wk-tl-head"><b>{{ $u->user?->name ?? 'Someone' }}</b> · {{ $u->created_at->format('d M Y, h:i A') }} <span class="text-muted">({{ $u->created_at->diffForHumans() }})</span></div>
                                    @include('tickets.partials.changes', ['changes' => $u->changes ?? [], 'statuses' => Task::STATUSES])
                                    @if ($u->body)<div class="wk-tl-note">{{ $u->body }}</div>@endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card acct-panel">
            <div class="card-body">
                <dl class="wk-facts">
                    <div><dt>Status</dt><dd><span class="wk-pill" style="--c: {{ $task->statusColor() }}"><i class="{{ $task->statusIcon() }}"></i> {{ $task->statusLabel() }}</span></dd></div>
                    <div><dt>Progress</dt><dd style="min-width: 8rem"><div class="wk-progress ml-auto" style="width: 8rem"><span style="width: {{ $task->progress }}%"></span></div><small>{{ $task->progress }}%</small></dd></div>
                    <div><dt>Priority</dt><dd><span class="wk-pri" style="--c: {{ $task->priorityColor() }}">{{ $task->priorityLabel() }}</span></dd></div>
                    <div><dt>For</dt><dd><span class="wk-av sm mr-1">{{ $task->assignee?->initials() }}</span>{{ $task->assignee?->name }}</dd></div>
                    <div><dt>Given by</dt><dd>{{ $task->creator?->name ?? '—' }}</dd></div>
                    <div><dt>Due date</dt><dd class="{{ $task->isOverdue() ? 'text-danger font-weight-bold' : '' }}">{{ $task->due_date?->format('d M Y') ?? '—' }}</dd></div>
                    @if ($task->completed_at)<div><dt>Done on</dt><dd>{{ $task->completed_at->format('d M Y, h:i A') }}</dd></div>@endif
                    @if ($task->ticket)<div><dt>Ticket</dt><dd><a href="{{ route('tickets.show', $task->ticket) }}">{{ $task->ticket->number() }}</a></dd></div>@endif
                    <div><dt>Created</dt><dd>{{ $task->created_at->format('d M Y, h:i A') }}</dd></div>
                </dl>
            </div>
        </div>

        @if ($canEdit)
            <div class="card acct-panel">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-pen mr-1 text-primary"></i> Edit</h3></div>
                <form method="POST" action="{{ route('my.tasks.update', $task) }}" class="card-body viewer-ok">
                    @csrf @method('PUT')
                    <div class="form-group"><label>Task title</label><input type="text" name="title" value="{{ $task->title }}" class="form-control" maxlength="255" required></div>
                    <div class="form-group"><label>Details</label><textarea name="description" rows="3" class="form-control" maxlength="5000">{{ $task->description }}</textarea></div>
                    <div class="form-row">
                        <div class="col-6 form-group"><label>Due date</label><input type="date" name="due_date" value="{{ $task->due_date?->toDateString() }}" class="form-control"></div>
                        <div class="col-6 form-group">
                            <label>Priority</label>
                            <select name="priority" class="form-control">
                                @foreach (Task::PRIORITIES as $k => [$pLabel])
                                    <option value="{{ $k }}" @selected($task->priority === $k)>{{ \App\Support\Ui::t($pLabel) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>For</label>
                        <select name="assigned_to" class="form-control">
                            @foreach ($users->contains('id', $task->assigned_to) ? $users : $users->push($task->assignee) as $u)
                                <option value="{{ $u->id }}" @selected($task->assigned_to === $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="btn btn-primary btn-block"><i class="fas fa-save"></i> Save</button>
                </form>
            </div>
        @endif

        @if ($canEdit || ($task->isPersonal() && $doing))
            <form method="POST" action="{{ route('my.tasks.destroy', $task) }}" class="js-confirm-delete text-center viewer-ok" data-confirm-message="Remove this task?">
                @csrf @method('DELETE')
                <input type="hidden" name="back" value="list">
                <button class="btn btn-link btn-sm text-danger"><i class="fas fa-trash"></i> Remove task</button>
            </form>
        @endif
    </div>
</div>

@stop
