{{-- Dashboard: everyone's own work — to-do list, tasks they gave, tickets, leave. --}}
@use('App\Models\Task')
@php
    $me = auth()->user();
    $tkd = fn ($v) => '৳' . preg_replace('/\.00$/', '', \App\Support\Dec::lakh(round((float) $v, 2)));
@endphp

<div class="dash-section"><i class="fas fa-briefcase"></i> My Work</div>

@if ($work['ticket_stats'] || $work['assets'] || $work['leave_pending'])
    <div class="kpi-grid">
        @if ($work['ticket_stats'])
            <a href="{{ route('tickets.index', ['view' => 'active']) }}" class="kpi">
                <div class="kpi-top">
                    <span class="kpi-label">Open Tickets</span>
                    <span class="kpi-icon tone-rose"><i class="fas fa-ticket-alt"></i></span>
                </div>
                <div class="kpi-value">{{ $work['ticket_stats']['open'] }}</div>
                <div class="kpi-foot">
                    @if ($work['ticket_stats']['overdue']) <span class="text-danger font-weight-bold">{{ $work['ticket_stats']['overdue'] }} overdue</span> · @endif
                    {{ $work['ticket_stats']['unassigned'] }} not assigned
                </div>
            </a>
        @endif
        @if ($work['assets'])
            <a href="{{ route('inventory.summary') }}" class="kpi">
                <div class="kpi-top">
                    <span class="kpi-label">Company Assets</span>
                    <span class="kpi-icon tone-sky"><i class="fas fa-boxes"></i></span>
                </div>
                <div class="kpi-value" style="font-size:1.45rem">{{ $tkd($work['assets']['company_assets']) }}</div>
                <div class="kpi-foot">
                    Store {{ $tkd($work['assets']['store_value']) }}
                    @if ($work['assets']['low']->isNotEmpty()) · <span class="text-danger font-weight-bold">{{ $work['assets']['low']->count() }} low stock</span> @endif
                </div>
            </a>
        @endif
        @if ($work['leave_pending'])
            <a href="{{ route('attendance.leaves.index', ['status' => 'pending']) }}" class="kpi">
                <div class="kpi-top">
                    <span class="kpi-label">Leave Requests</span>
                    <span class="kpi-icon tone-amber"><i class="fas fa-user-clock"></i></span>
                </div>
                <div class="kpi-value">{{ $work['leave_pending'] }}</div>
                <div class="kpi-foot"><span class="text-warning font-weight-bold">Waiting for approval</span></div>
            </a>
        @endif
    </div>
@endif

<div class="row">
    {{-- My to-do list --}}
    <div class="col-lg-7">
        <div class="card dash-panel acct-panel">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-check-square mr-1 text-teal"></i> My to-do list</h3>
                <div class="small">
                    @if ($work['overdue'])<span class="text-danger font-weight-bold mr-2"><i class="fas fa-fire"></i> {{ $work['overdue'] }} overdue</span>@endif
                    <a href="{{ route('my.tasks.index') }}">All ({{ $work['open'] }})</a>
                </div>
            </div>
            <form method="POST" action="{{ route('my.tasks.store') }}" class="wk-quick viewer-ok">
                @csrf
                <input type="text" name="title" class="form-control" maxlength="255" required placeholder="Add a to-do and press Enter…">
                <input type="date" name="due_date" class="form-control" style="max-width: 10rem" title="Due date">
                <button class="btn btn-primary"><i class="fas fa-plus"></i></button>
            </form>
            <div class="card-body p-0" data-live="my-tasks">
                @forelse ($work['tasks'] as $task)
                    <div @class(['wk-task', 'done' => $task->status === 'done'])>
                        <form method="POST" action="{{ route('my.tasks.toggle', $task) }}" class="viewer-ok">
                            @csrf
                            <button class="wk-check" title="{{ $task->status === 'done' ? \App\Support\Ui::t('Mark not done') : \App\Support\Ui::t('Mark done') }}"><i class="fas fa-check"></i></button>
                        </form>
                        <div class="wk-task-body">
                            <a href="{{ route('my.tasks.show', $task) }}" class="wk-task-title">{{ $task->title }}</a>
                            <div class="wk-meta">
                                @if ($task->priority !== 'normal')<span class="wk-pri" style="--c: {{ $task->priorityColor() }}">{{ $task->priorityLabel() }}</span>@endif
                                @if ($task->due_date)
                                    <span @class(['late' => $task->isOverdue(), 'today' => $task->isOpen() && $task->due_date->isToday()])>
                                        <i class="far fa-calendar"></i> {{ $task->due_date->isToday() ? \App\Support\Ui::t('Today') : $task->due_date->format('d M') }}
                                    </span>
                                @endif
                                @unless ($task->isPersonal())<span title="{{ \App\Support\Ui::t('Given by') }}"><i class="fas fa-user-tag"></i> {{ $task->creator?->name }}</span>@endunless
                                @if ($task->ticket)<span><i class="fas fa-ticket-alt"></i> {{ $task->ticket->number() }}</span>@endif
                            </div>
                        </div>
                        @unless ($task->isPersonal())
                            <div class="wk-task-side">
                                <span class="wk-pill" style="--c: {{ $task->statusColor() }}">{{ $task->statusLabel() }}</span>
                            </div>
                        @endunless
                    </div>
                @empty
                    <div class="acct-empty"><i class="fas fa-mug-hot"></i>Nothing on your list — add a to-do above.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        {{-- Tickets given to me --}}
        @if ($work['tickets']->isNotEmpty() || $work['ticket_stats'])
            <div class="card dash-panel acct-panel">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-ticket-alt mr-1 text-danger"></i> My tickets</h3>
                    <a href="{{ route('tickets.index', ['view' => 'mine']) }}" class="small">All</a>
                </div>
                <div class="card-body p-0" data-live="my-tickets">
                    @forelse ($work['tickets'] as $t)
                        <a href="{{ route('tickets.show', $t) }}" class="acct-list-row">
                            <span class="wk-cat"><i class="{{ $t->categoryIcon() }}"></i></span>
                            <div class="acct-list-main" style="min-width:0">
                                <div class="acct-list-title text-truncate">{{ $t->subject }}</div>
                                <div class="acct-list-sub">
                                    {{ $t->number() }} · {{ $t->customer?->name ?? $t->zone ?? $t->contact_name ?? '—' }}
                                    @if ($t->isOverdue()) · <span class="text-danger font-weight-bold">{{ \App\Support\Ui::t('late') }}</span>@endif
                                </div>
                            </div>
                            <span class="wk-pri" style="--c: {{ $t->priorityColor() }}">{{ $t->priorityLabel() }}</span>
                        </a>
                    @empty
                        <div class="acct-empty py-3"><i class="fas fa-check-circle" style="color:#86efac"></i>No open tickets given to you.</div>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- Tasks I gave others: where each one is --}}
        @if ($work['given']->isNotEmpty() || $work['can_assign'])
            <div class="card dash-panel acct-panel">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-share-square mr-1 text-primary"></i> Tasks I gave</h3>
                    <a href="{{ route('my.tasks.index', ['tab' => 'given']) }}" class="small">All</a>
                </div>
                <div class="card-body p-0" data-live="my-given">
                    @forelse ($work['given'] as $task)
                        <a href="{{ route('my.tasks.show', $task) }}" class="acct-list-row">
                            <span class="wk-av sm">{{ $task->assignee?->initials() }}</span>
                            <div class="acct-list-main" style="min-width:0">
                                <div class="acct-list-title text-truncate">{{ $task->title }}</div>
                                <div class="acct-list-sub d-flex align-items-center" style="gap:.5rem">
                                    {{ $task->assignee?->name }}
                                    <span class="wk-progress" style="width:4.5rem"><span style="width: {{ $task->progress }}%"></span></span> {{ $task->progress }}%
                                </div>
                            </div>
                            <span class="wk-pill" style="--c: {{ $task->statusColor() }}">{{ $task->statusLabel() }}</span>
                        </a>
                    @empty
                        <div class="acct-empty py-3"><i class="fas fa-share-square"></i>Give someone a task from <a href="{{ route('my.tasks.index', ['tab' => 'given']) }}">My Tasks</a>.</div>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- Leave --}}
        @if ($work['leave'])
            <div class="card dash-panel acct-panel">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-umbrella-beach mr-1 text-info"></i> My leave</h3>
                    <a href="{{ route('my.leave.index') }}" class="btn btn-outline-primary btn-xs"><i class="fas fa-paper-plane"></i> Apply for leave</a>
                </div>
                <div class="card-body">
                    @if ($work['leave']['today'])
                        <div class="alert alert-info py-2 small mb-2"><i class="fas fa-umbrella-beach"></i> You're on leave today.</div>
                    @endif
                    <div class="d-flex flex-wrap" style="gap:1.25rem">
                        @foreach ($work['leave']['balances'] as $b)
                            <div>
                                <div class="small text-muted">{{ \App\Support\Ui::t($b['name']) }}</div>
                                <div class="font-weight-bold" style="font-size:1.15rem">{{ $b['left'] }} <small class="text-muted">/ {{ $b['allocated'] }}</small></div>
                            </div>
                        @endforeach
                    </div>
                    @if ($l = $work['leave']['latest'])
                        <div class="small mt-2 pt-2 border-top">
                            {{ \App\Support\Ui::t('Last request') }}: {{ $l->start_date->format('d M') }}{{ $l->end_date->ne($l->start_date) ? ' – ' . $l->end_date->format('d M') : '' }}
                            · {{ \App\Support\Ui::t($l->leaveType?->name) }}
                            <span class="wk-pill ml-1" style="--c: {{ ['pending' => '#d97706', 'approved' => '#16a34a', 'rejected' => '#dc2626'][$l->status] ?? '#64748b' }}">{{ \App\Support\Ui::t(ucfirst($l->status)) }}</span>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
