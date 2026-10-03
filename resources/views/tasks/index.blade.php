@extends('adminlte::page')

@section('title', 'My Tasks & To-do')

@use('App\Models\Task')
@php
    $user = auth()->user();
    $canAssign = $user->can('access-tasks-assign');
    $tabs = array_filter([
        'mine' => ['My list', 'fas fa-check-square', $counts['mine']],
        'given' => $canAssign || $counts['given'] ? ['Given to others', 'fas fa-share-square', $counts['given']] : null,
        'board' => $user->can('access-tasks-board') ? ['Team board', 'fas fa-users', $counts['board']] : null,
    ]);
@endphp

@section('content_header')
<x-work.header title="My Tasks & To-do" icon="fas fa-check-square" subtitle="Your own to-do list, the tasks people gave you, and the ones you gave — with where each one is" />
@stop

@section('content')

@include('inventory.partials.alerts')

<div class="acct-stats">
    <div class="acct-stat" style="--accent:#4f46e5">
        <div class="acct-stat-label">To do <i class="fas fa-list-ul"></i></div>
        <div class="acct-stat-value">{{ $counts['mine'] }}</div>
        <div class="acct-stat-foot">Open on your list</div>
    </div>
    <div class="acct-stat" style="--accent:#d97706">
        <div class="acct-stat-label">Due today <i class="fas fa-calendar-day"></i></div>
        <div class="acct-stat-value">{{ $counts['today'] }}</div>
        <div class="acct-stat-foot">{{ \App\Support\Ui::day(now()) }}, {{ now()->format('d/m') }}</div>
    </div>
    <div class="acct-stat" style="--accent:{{ $counts['overdue'] ? '#dc2626' : '#64748b' }}">
        <div class="acct-stat-label">Overdue <i class="fas fa-fire"></i></div>
        <div class="acct-stat-value {{ $counts['overdue'] ? 'text-danger' : '' }}">{{ $counts['overdue'] }}</div>
        <div class="acct-stat-foot">Past their due date</div>
    </div>
    <div class="acct-stat" style="--accent:#16a34a">
        <div class="acct-stat-label">Done this week <i class="fas fa-check-double"></i></div>
        <div class="acct-stat-value">{{ $counts['done_week'] }}</div>
        <div class="acct-stat-foot">Keep going!</div>
    </div>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between" style="gap:.5rem">
    <div class="wk-tabs">
        @foreach ($tabs as $key => [$tLabel, $tIcon, $n])
            <a href="{{ route('my.tasks.index', ['tab' => $key]) }}" @class(['active' => $tab === $key])><i class="{{ $tIcon }}"></i> {{ \App\Support\Ui::t($tLabel) }} <b>{{ $n }}</b></a>
        @endforeach
    </div>
    <a href="{{ route('my.tasks.index', array_filter(['tab' => $tab, 'done' => $showDone ? null : 1, 'person' => request('person')])) }}" class="small mb-3">
        <i class="fas {{ $showDone ? 'fa-eye-slash' : 'fa-eye' }}"></i> {{ $showDone ? \App\Support\Ui::t('Hide finished') : \App\Support\Ui::t('Show finished') }}
    </a>
</div>

@if ($tab === 'board')
    <div class="d-flex flex-wrap mb-3" style="gap:.5rem">
        <a href="{{ route('my.tasks.index', ['tab' => 'board']) }}" class="btn btn-sm {{ request('person') ? 'btn-light border' : 'btn-primary' }}">Everyone</a>
        @foreach ($people as $p)
            <a href="{{ route('my.tasks.index', ['tab' => 'board', 'person' => $p['user']->id]) }}" class="btn btn-sm {{ request('person') == $p['user']->id ? 'btn-primary' : 'btn-light border' }}">
                <span class="wk-av sm mr-1">{{ $p['user']->initials() }}</span>{{ $p['user']->name }}
                <span class="badge badge-light ml-1">{{ $p['open'] }}</span>@if ($p['overdue'])<span class="badge badge-danger ml-1">{{ $p['overdue'] }}</span>@endif
            </a>
        @endforeach
    </div>
@endif

<div class="card acct-panel">
    {{-- Quick add --}}
    @if ($tab !== 'board')
        <form method="POST" action="{{ route('my.tasks.store') }}" class="wk-quick viewer-ok">
            @csrf
            <input type="text" name="title" class="form-control" maxlength="255" required
                   placeholder="{{ $tab === 'given' ? \App\Support\Ui::t('Give a task — e.g. Check Navaron OLT power backup') : \App\Support\Ui::t('Add a to-do — e.g. Call MY NET about the September bill') }}">
            @if ($canAssign)
                <select name="assigned_to" class="form-control" style="max-width: 12rem">
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected($tab === 'given' ? false : $u->id === $user->id)>{{ $u->id === $user->id ? \App\Support\Ui::t('Me') : $u->name }}</option>
                    @endforeach
                </select>
            @endif
            <input type="date" name="due_date" class="form-control" style="max-width: 10rem" title="Due date">
            <select name="priority" class="form-control" style="max-width: 8rem">
                @foreach (Task::PRIORITIES as $k => [$pLabel])
                    <option value="{{ $k }}" @selected($k === 'normal')>{{ \App\Support\Ui::t($pLabel) }}</option>
                @endforeach
            </select>
            <button class="btn btn-primary text-nowrap"><i class="fas fa-plus"></i> Add</button>
        </form>
    @endif

    <div class="card-body p-0">
        @forelse ($tasks as $task)
            @php
                $mineToDo = $task->assigned_to === $user->id;
                $due = $task->due_date;
            @endphp
            <div @class(['wk-task', 'done' => $task->status === 'done', 'cancelled' => $task->status === 'cancelled'])>
                @if ($mineToDo || $task->created_by === $user->id)
                    <form method="POST" action="{{ route('my.tasks.toggle', $task) }}" class="viewer-ok">
                        @csrf
                        <button class="wk-check" title="{{ $task->status === 'done' ? \App\Support\Ui::t('Mark not done') : \App\Support\Ui::t('Mark done') }}"><i class="fas fa-check"></i></button>
                    </form>
                @else
                    <span class="wk-check" style="cursor: default"><i class="fas fa-check"></i></span>
                @endif

                <div class="wk-task-body">
                    <a href="{{ route('my.tasks.show', $task) }}" class="wk-task-title">{{ $task->title }}</a>
                    <div class="wk-meta">
                        <span class="wk-pri" style="--c: {{ $task->priorityColor() }}">{{ $task->priorityLabel() }}</span>
                        @if ($due)
                            <span @class(['late' => $task->isOverdue(), 'today' => $task->isOpen() && $due->isToday()])>
                                <i class="far fa-calendar"></i> {{ $due->isToday() ? \App\Support\Ui::t('Today') : ($due->isTomorrow() ? \App\Support\Ui::t('Tomorrow') : $due->format('d M')) }}
                                @if ($task->isOverdue()) · {{ \App\Support\Ui::t('late') }} @endif
                            </span>
                        @endif
                        @if (! $task->isPersonal())
                            @if ($mineToDo)
                                <span title="{{ \App\Support\Ui::t('Given by') }}"><i class="fas fa-user-tag"></i> {{ $task->creator?->name }}</span>
                            @else
                                <span><span class="wk-av sm">{{ $task->assignee?->initials() }}</span> {{ $task->assignee?->name }}</span>
                            @endif
                        @endif
                        @if ($task->ticket)
                            <a href="{{ route('tickets.show', $task->ticket) }}"><i class="fas fa-ticket-alt"></i> {{ $task->ticket->number() }}</a>
                        @endif
                        @if ($task->updates_count)
                            <span><i class="far fa-comment-dots"></i> {{ $task->updates_count }}</span>
                        @endif
                    </div>
                </div>

                <div class="wk-task-side">
                    <span class="wk-pill" style="--c: {{ $task->statusColor() }}"><i class="{{ $task->statusIcon() }}"></i> {{ $task->statusLabel() }}</span>
                    @if (! $task->isPersonal() && $task->status !== 'cancelled')
                        <div class="wk-progress" title="{{ $task->progress }}%"><span style="width: {{ $task->progress }}%"></span></div>
                    @endif
                </div>
            </div>
        @empty
            <div class="acct-empty">
                <i class="fas fa-mug-hot"></i>
                @switch($tab)
                    @case('given') You haven't given anyone a task. @break
                    @case('board') Nobody has open tasks. @break
                    @default Nothing to do — add your first to-do above.
                @endswitch
            </div>
        @endforelse
    </div>
</div>

@stop
