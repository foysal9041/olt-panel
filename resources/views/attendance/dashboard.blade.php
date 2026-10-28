@extends('adminlte::page')

@section('title', 'Attendance Dashboard')

@php
    // Today's working staff: not on leave, not on their weekly off (those who came in anyway are overtime).
    // In office = everyone who punched today: on time, late, or in on their off day (overtime).
    $in = $summary['present'] + $summary['late'] + ($summary['overtime'] ?? 0);
    $expected = max(0, $summary['total'] - ($summary['leave'] ?? 0) - ($summary['weekend'] ?? 0) - ($summary['overtime'] ?? 0));
    $rate = $expected > 0 ? min(100, round(($summary['present'] + $summary['late']) / $expected * 100)) : 0;
    $onlineDevices = $devices->filter->isOnline()->count();
    $statusMeta = [
        'present' => ['Present', 'success'],
        'late' => ['Late', 'warning'],
        'leave' => ['On Leave', 'info'],
        'absent' => ['Absent', 'danger'],
        'weekend' => ['Weekly Off', 'secondary'],
        'overtime' => ['Overtime', 'primary'],
        'upcoming' => ['—', 'secondary'],
    ];
    $dayLabel = fn ($d) => $d->isToday() ? \App\Support\Ui::t('Today') : (\App\Support\Ui::isBangla() ? \App\Support\Ui::day($d) : $d->format('D'));
    $rateColor = fn ($r) => $r === null ? '#94a3b8' : ($r >= 90 ? '#16a34a' : ($r >= 70 ? '#d97706' : '#e11d48'));
    $canReport = auth()->user()->can('access-attendance-report');
    $weekFrom = \Illuminate\Support\Carbon::parse($trend[0]['date']);
    $weekTo = \Illuminate\Support\Carbon::parse(end($trend)['date']);
    $chartData = [
        'labels' => array_map(fn ($day) => $dayLabel($d = \Illuminate\Support\Carbon::parse($day['date'])) . ' ' . (\App\Support\Ui::isBangla() ? \App\Support\Bangla::digits($d->format('j')) : $d->format('j')), $trend),
        'on_time' => array_column($trend, 'on_time'),
        'late' => array_column($trend, 'late'),
        'overtime' => array_column($trend, 'overtime'),
        'leave' => array_column($trend, 'leave'),
        'absent' => array_column($trend, 'absent'),
        'rate' => array_column($trend, 'rate'),
    ];
@endphp

@section('content_header')
<x-attendance.header title="Attendance" icon="fas fa-fingerprint"
    subtitle="{{ now()->format('l, d F Y') }} — live from {{ $devices->count() }} {{ Str::plural('device', $devices->count()) }}">
    @can('access-attendance-report')
        <a href="{{ route('attendance.report') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-calendar-check"></i> Report
        </a>
    @endcan
    @can('access-attendance-leaves')
        <a href="{{ route('attendance.leaves.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Leave
        </a>
    @endcan
</x-attendance.header>
@stop

@section('css')
<style>
    .att-board td { vertical-align: middle; }
    .att-time { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .att-device { display: flex; align-items: center; gap: .6rem; padding: .65rem 1.15rem; border-bottom: 1px solid #f1f5f9; }
    .att-device:last-child { border-bottom: 0; }
    .att-dot { width: .6rem; height: .6rem; border-radius: 50%; flex: none; }
    .att-dot.on { background: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, .2); }
    .att-dot.off { background: #f43f5e; }

    /* Last 7 days */
    .att-week-plot { position: relative; flex: 1 1 auto; min-height: 260px; }
    .att-days { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); border-top: 1px solid #eef2f7; }
    .att-days > div { padding: .55rem .25rem; text-align: center; min-width: 0; }
    .att-days > div + div { border-left: 1px solid #f1f5f9; }
    .att-days > div.is-today { background: #f8fafc; }
    .att-days span { display: block; font-size: .68rem; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .att-days b { display: block; font-size: .95rem; font-variant-numeric: tabular-nums; }
    .att-days small { display: block; font-size: .7rem; color: #94a3b8; font-variant-numeric: tabular-nums; }

    /* This week */
    .att-week-top { display: flex; align-items: center; gap: 1.1rem; padding: 1.1rem 1.15rem .9rem; }
    .att-ring { --p: 0; --c: #16a34a; flex: none; display: grid; place-items: center; width: 7.6rem; height: 7.6rem; border-radius: 50%; background: conic-gradient(var(--c) calc(var(--p) * 1%), #eef2f7 0); }
    .att-ring::before { content: ''; grid-area: 1 / 1; width: 6rem; height: 6rem; border-radius: 50%; background: #fff; }
    .att-ring > div { grid-area: 1 / 1; position: relative; text-align: center; line-height: 1.1; }
    .att-ring b { display: block; font-size: 1.65rem; font-weight: 800; color: #0f172a; font-variant-numeric: tabular-nums; }
    .att-ring span { font-size: .66rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #64748b; }
    .att-wk-stats { flex: 1; min-width: 0; display: grid; gap: .6rem; }
    .att-wk-stat { display: flex; align-items: center; gap: .6rem; min-width: 0; }
    .att-wk-stat > i { flex: none; display: grid; place-items: center; width: 2rem; height: 2rem; border-radius: .6rem; background: color-mix(in srgb, var(--accent) 12%, #fff); color: var(--accent); font-size: .8rem; }
    .att-wk-stat small { display: block; font-size: .66rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #94a3b8; }
    .att-wk-stat b { display: block; font-size: .92rem; color: #0f172a; font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .att-kicker { padding: .7rem 1.15rem .2rem; border-top: 1px solid #eef2f7; font-size: .68rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #94a3b8; }
    .att-week .acct-list-row { padding-top: .5rem; padding-bottom: .5rem; }

    /* Avatars coloured by today's status */
    .att-av-present { background: #dcfce7; color: #15803d; }
    .att-av-late { background: #fef3c7; color: #b45309; }
    .att-av-absent { background: #ffe4e6; color: #be123c; }
    .att-av-leave { background: #e0f2fe; color: #0369a1; }
    .att-av-overtime { background: #e0e7ff; color: #4338ca; }
    .att-av-weekend, .att-av-upcoming { background: #f1f5f9; color: #64748b; }
</style>
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

{{-- ============ Last 7 days ============ --}}
<div class="row">

    <div class="col-xl-8 d-flex">
        <div class="card acct-panel w-100">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-bar mr-1 text-primary"></i> Last 7 Days</h3>
                <span class="small text-muted">
                    {{ $weekFrom->format('d M') }} – {{ $weekTo->format('d M') }}
                    @if ($canReport)
                        · <a href="{{ route('attendance.report', ['start' => $weekFrom->toDateString(), 'end' => $weekTo->toDateString()]) }}">{{ \App\Support\Ui::t('Full report') }}</a>
                    @endif
                </span>
            </div>
            <div class="card-body pb-2 d-flex flex-column">
                <div class="att-week-plot">
                    <canvas id="attendanceTrendChart"></canvas>
                </div>
            </div>
            {{-- Each day's attendance: who came in of those expected --}}
            <div class="att-days" data-live="att-days">
                @foreach ($trend as $day)
                    @php $d = \Illuminate\Support\Carbon::parse($day['date']); @endphp
                    <div class="{{ $d->isToday() ? 'is-today' : '' }}">
                        <span>{{ $dayLabel($d) }}</span>
                        <b style="color: {{ $rateColor($day['rate']) }}">{{ $day['rate'] === null ? '—' : $day['rate'] . '%' }}</b>
                        <small>{{ $day['present'] }}/{{ $day['expected'] }}</small>
                    </div>
                @endforeach
            </div>
            <div hidden data-live="att-trend">{{ json_encode($chartData) }}</div>
        </div>
    </div>

    <div class="col-xl-4 d-flex">
        <div class="card acct-panel att-week w-100">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-calendar-week mr-1 text-primary"></i> This Week</h3>
                <span class="small text-muted">last 7 days</span>
            </div>
            <div class="card-body p-0" data-live="att-week">
                <div class="att-week-top">
                    <div class="att-ring" style="--p: {{ $week['rate'] ?? 0 }}; --c: {{ $rateColor($week['rate']) }}">
                        <div>
                            <b>{{ $week['rate'] === null ? '—' : $week['rate'] . '%' }}</b>
                            <span>attendance</span>
                        </div>
                    </div>
                    <div class="att-wk-stats">
                        <div class="att-wk-stat" style="--accent:#d97706">
                            <i class="fas fa-clock"></i>
                            <div style="min-width:0">
                                <small>Late</small>
                                <b>{{ $week['late_days'] }} {{ \App\Support\Ui::t($week['late_days'] === 1 ? 'day' : 'days') }}@if ($week['late_minutes']) · {{ \App\Support\Ui::minutes($week['late_minutes']) }}@endif</b>
                            </div>
                        </div>
                        <div class="att-wk-stat" style="--accent:#e11d48">
                            <i class="fas fa-user-times"></i>
                            <div style="min-width:0">
                                <small>Absent</small>
                                <b>{{ $week['absent'] }} {{ \App\Support\Ui::t($week['absent'] === 1 ? 'day' : 'days') }}</b>
                            </div>
                        </div>
                        <div class="att-wk-stat" style="--accent:#4f46e5">
                            <i class="fas fa-business-time"></i>
                            <div style="min-width:0">
                                <small>Extra duty</small>
                                <b>{{ $week['extra_minutes'] ? \App\Support\Ui::minutes($week['extra_minutes']) : '—' }}</b>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="att-kicker">Most late</div>
                @forelse ($week['most_late'] as $t)
                    <a class="acct-list-row" @if ($canReport) href="{{ route('attendance.report', ['start' => $weekFrom->toDateString(), 'end' => $weekTo->toDateString(), 'employee_id' => $t['employee']->id]) }}" @endif>
                        <span class="acct-avatar att-av-late">{{ mb_substr($t['employee']->name, 0, 2) }}</span>
                        <span class="acct-list-main">
                            <div class="acct-list-title">{{ $t['employee']->name }}</div>
                            <div class="acct-list-sub">{{ $t['late'] }} {{ \App\Support\Ui::t($t['late'] === 1 ? 'day late' : 'days late') }}</div>
                        </span>
                        <span class="acct-list-amount text-warning">{{ \App\Support\Ui::minutes($t['late_minutes']) }}</span>
                    </a>
                @empty
                    <div class="px-3 pb-3 pt-1 small text-muted"><i class="fas fa-check-circle text-success"></i> No one was late this week.</div>
                @endforelse

                @if ($week['most_extra']->isNotEmpty())
                    <div class="att-kicker">Most extra duty</div>
                    @foreach ($week['most_extra'] as $t)
                        <a class="acct-list-row" @if ($canReport) href="{{ route('attendance.report', ['start' => $weekFrom->toDateString(), 'end' => $weekTo->toDateString(), 'employee_id' => $t['employee']->id]) }}" @endif>
                            <span class="acct-avatar att-av-overtime">{{ mb_substr($t['employee']->name, 0, 2) }}</span>
                            <span class="acct-list-main">
                                <div class="acct-list-title">{{ $t['employee']->name }}</div>
                                <div class="acct-list-sub">
                                    @if ($t['overtime_days'])
                                        {{ $t['overtime_days'] }} {{ \App\Support\Ui::t($t['overtime_days'] === 1 ? 'off day worked' : 'off days worked') }}
                                    @else
                                        {{ \App\Support\Ui::t('after shift') }}
                                    @endif
                                </div>
                            </span>
                            <span class="acct-list-amount text-primary">+{{ \App\Support\Ui::minutes($t['extra_minutes']) }}</span>
                        </a>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

</div>

{{-- ============ Today ============ --}}
<div class="acct-stats" data-live="att-stats">

    <div class="acct-stat att-tile" style="--accent:#4f46e5" data-show="in" data-title="In Office" role="button" title="Click to see who">
        <div class="acct-stat-label">In Office <i class="fas fa-user-check"></i></div>
        <div class="acct-stat-value">{{ $in }} <small class="text-muted" style="font-size:1rem">/ {{ $expected }}</small></div>
        <div class="acct-stat-foot">{{ $rate }}% attendance today</div>
        <div class="acct-progress"><span style="width: {{ $rate }}%"></span></div>
    </div>

    <div class="acct-stat att-tile" style="--accent:#16a34a" data-show="present" data-title="On Time" role="button" title="Click to see who">
        <div class="acct-stat-label">On Time <i class="fas fa-check"></i></div>
        <div class="acct-stat-value">{{ $summary['present'] }}</div>
        <div class="acct-stat-foot">Checked in before the grace time</div>
    </div>

    <div class="acct-stat att-tile" style="--accent:#d97706" data-show="late" data-title="Late" role="button" title="Click to see who">
        <div class="acct-stat-label">Late <i class="fas fa-clock"></i></div>
        <div class="acct-stat-value">{{ $summary['late'] }}</div>
        <div class="acct-stat-foot">After shift start + grace</div>
    </div>

    <div class="acct-stat att-tile" style="--accent:#e11d48" data-show="absent" data-title="Absent" role="button" title="Click to see who">
        <div class="acct-stat-label">Absent <i class="fas fa-user-times"></i></div>
        <div class="acct-stat-value">{{ $summary['absent'] }}</div>
        <div class="acct-stat-foot">No punch yet today</div>
    </div>

    @if (($summary['overtime'] ?? 0) > 0)
        <div class="acct-stat att-tile" style="--accent:#4f46e5" data-show="overtime" data-title="Overtime" role="button" title="Click to see who">
            <div class="acct-stat-label">Overtime <i class="fas fa-business-time"></i></div>
            <div class="acct-stat-value">{{ $summary['overtime'] }}</div>
            <div class="acct-stat-foot">Came in on their weekly off day</div>
        </div>
    @endif

    <div class="acct-stat att-tile" style="--accent:#0284c7" data-show="leave" data-title="On Leave" role="button" title="Click to see who">
        <div class="acct-stat-label">On Leave <i class="fas fa-plane-departure"></i></div>
        <div class="acct-stat-value">{{ $summary['leave'] ?? 0 }}</div>
        <div class="acct-stat-foot">
            @if ($pendingLeaves->isNotEmpty())
                <span class="text-warning font-weight-bold">{{ $pendingLeaves->count() }} pending approval</span>
            @else
                Approved leave today
            @endif
        </div>
    </div>

</div>



<div class="row">

    {{-- ============ Today's board ============ --}}
    <div class="col-lg-8">
        <div class="card acct-panel">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-clock mr-1 text-primary"></i> Today's Attendance</h3>
                <span class="text-muted small" data-live="att-board-count">{{ $board->count() }} employees</span>
            </div>
            <div class="card-body p-0 table-responsive" style="max-height: 460px; overflow-y: auto;">
                <table class="table table-hover att-board mb-0">
                    <thead style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th class="pl-3">Employee</th>
                            <th>Status</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th class="text-right pr-3">Hours</th>
                        </tr>
                    </thead>
                    <tbody data-live="att-board">
                        @forelse ($board as $row)
                            @php [$label, $color] = $statusMeta[$row['status']] ?? [ucfirst($row['status']), 'secondary']; @endphp
                            <tr data-status="{{ $row['status'] }}">
                                <td class="pl-3">
                                    <div class="d-flex align-items-center">
                                        <span class="acct-avatar att-av-{{ $row['status'] }} mr-2">{{ mb_substr($row['employee']->name, 0, 2) }}</span>
                                        <div>
                                            <div class="font-weight-bold" style="color:#0f172a">{{ $row['employee']->name }}</div>
                                            <div class="small text-muted">
                                                {{ $row['employee']->designation ?: 'Employee' }}
                                                @if ($row['employee']->dutyShift) · {{ $row['employee']->dutyShift->name }} @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-{{ $color }}">{{ $label }}</span>
                                    @if (! empty($row['late_minutes']))
                                        <div class="small text-warning">{{ \App\Support\Ui::minutes($row['late_minutes']) }} {{ \App\Support\Ui::t('late') }}</div>
                                    @endif
                                    @if (! empty($row['extra_minutes']))
                                        <div class="small text-primary">+{{ \App\Support\Ui::minutes($row['extra_minutes']) }} {{ \App\Support\Ui::t('extra') }}</div>
                                    @endif
                                </td>
                                <td class="att-time">{{ $row['check_in']?->format('h:i A') ?? '—' }}</td>
                                <td class="att-time">{{ $row['check_out']?->format('h:i A') ?? '—' }}</td>
                                <td class="att-time text-right pr-3">{{ $row['hours_worked'] !== null ? number_format($row['hours_worked'], 1) . 'h' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="acct-empty">No employees are linked to a device yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    {{-- ============ Side column ============ --}}
    <div class="col-lg-4">

        <div class="card acct-panel">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-microchip mr-1 text-primary"></i> Devices</h3>
                <span class="small {{ $onlineDevices < $devices->count() ? 'text-danger' : 'text-success' }}" data-live="att-dev-count">
                    {{ $onlineDevices }} / {{ $devices->count() }} online
                </span>
            </div>
            <div class="card-body p-0" data-live="att-devices">
                @forelse ($devices as $device)
                    <div class="att-device">
                        <span class="att-dot {{ $device->isOnline() ? 'on' : 'off' }}"></span>
                        <div class="flex-grow-1" style="min-width:0">
                            <div class="font-weight-bold text-truncate" style="color:#0f172a">{{ $device->name ?: $device->serial_number }}</div>
                            <div class="small text-muted">
                                {{ $device->zone ?: $device->serial_number }}
                                · {{ $device->last_seen_at ? 'seen ' . $device->last_seen_at->diffForHumans() : 'never connected' }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="acct-empty"><i class="fas fa-microchip"></i>No devices added yet.</div>
                @endforelse
            </div>
        </div>

        <div data-live="att-leaves">
        @if ($pendingLeaves->isNotEmpty())
            <div class="card acct-panel">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-plane-departure mr-1 text-warning"></i> Pending Leave Requests</h3>
                    @can('access-attendance-leaves')
                        <a href="{{ route('attendance.leaves.index', ['status' => 'pending']) }}" class="small">Review</a>
                    @endcan
                </div>
                <div class="card-body p-0">
                    @foreach ($pendingLeaves as $leave)
                        <div class="acct-list-row">
                            <span class="acct-avatar" style="background:#fef3c7;color:#b45309">{{ mb_substr($leave->employee->name ?? '?', 0, 2) }}</span>
                            <span class="acct-list-main">
                                <div class="acct-list-title">{{ $leave->employee->name ?? 'Unknown' }}</div>
                                <div class="acct-list-sub">
                                    {{ \App\Support\Ui::t($leave->leaveType->name ?? 'Leave') }} ·
                                    {{ $leave->start_date->format('d M') }}@if (! $leave->start_date->equalTo($leave->end_date)) – {{ $leave->end_date->format('d M') }}@endif
                                </div>
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
        </div>

        <div class="card acct-panel">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-fingerprint mr-1 text-primary"></i> Recent Punches</h3>
            </div>
            <div class="card-body p-0" style="max-height: 380px; overflow-y: auto;" data-live="att-punches">
                @forelse($recentPunches as $punch)
                    <div class="acct-list-row">
                        <span class="acct-avatar" style="background:#f1f5f9;color:#475569"><i class="fas fa-fingerprint"></i></span>
                        <span class="acct-list-main">
                            <div class="acct-list-title">{{ $punch->employee->name ?? 'Unknown' }}</div>
                            <div class="acct-list-sub">{{ $punch->zkDevice->name ?? $punch->zkDevice->serial_number ?? '-' }}</div>
                        </span>
                        <span class="text-right small">
                            <div class="font-weight-bold att-time" style="color:#0f172a">
                                @if (($punchRoles[$punch->id] ?? null) === 'in')
                                    <span class="badge badge-success" title="First punch of the day">IN</span>
                                @elseif (($punchRoles[$punch->id] ?? null) === 'out')
                                    <span class="badge badge-primary" title="Last punch of the day">OUT</span>
                                @elseif (($punchRoles[$punch->id] ?? null) === 'ignored')
                                    <span class="badge badge-light border" title="Outside office time — not counted for attendance">NOT COUNTED</span>
                                @endif
                                {{ $punch->punched_at->format('h:i A') }}
                            </div>
                            <div class="text-muted">{{ $punch->punched_at->isToday() ? 'Today' : $punch->punched_at->format('d M') }}</div>
                        </span>
                    </div>
                @empty
                    <div class="acct-empty"><i class="fas fa-fingerprint"></i>No punches recorded yet.</div>
                @endforelse
            </div>
        </div>

    </div>

</div>



@stop

@section('js')
{{-- Who is behind each number: the matching rows of today's board. --}}
<div class="modal fade" id="att-list" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><span id="att-list-title"></span> <span class="badge badge-light border" id="att-list-count"></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-hover att-board mb-0">
                    <thead class="thead-light"><tr><th class="pl-3">Employee</th><th>Status</th><th>Check In</th><th>Check Out</th><th class="text-right pr-3">Hours</th></tr></thead>
                    <tbody id="att-list-body"></tbody>
                </table>
                <div class="acct-empty py-4" id="att-list-empty" hidden><i class="fas fa-user-friends"></i>No one right now.</div>
            </div>
        </div>
    </div>
</div>
<style>
    .att-tile { cursor: pointer; transition: box-shadow .15s, transform .15s; }
    .att-tile:hover { box-shadow: 0 0 0 2px var(--accent, #4f46e5); transform: translateY(-1px); }
</style>
<script>
(function () {
    var groups = { in: ['present', 'late', 'overtime'], present: ['present'], late: ['late'], absent: ['absent'], overtime: ['overtime'], leave: ['leave'] };
    function show(key, title) {
        var want = groups[key] || [key];
        var rows = Array.prototype.filter.call(document.querySelectorAll('[data-live="att-board"] tr[data-status]'), function (tr) { return want.indexOf(tr.getAttribute('data-status')) >= 0; });
        var body = document.getElementById('att-list-body');
        body.innerHTML = '';
        rows.forEach(function (tr) { body.appendChild(tr.cloneNode(true)); });
        document.getElementById('att-list-title').textContent = title;
        document.getElementById('att-list-count').textContent = rows.length;
        document.getElementById('att-list-empty').hidden = rows.length > 0;
        $('#att-list').modal('show');
    }
    // The tiles are replaced by the live refresh, so listen on the page.
    document.addEventListener('click', function (e) {
        var tile = e.target.closest('.att-tile[data-show]');
        if (tile) show(tile.getAttribute('data-show'), tile.querySelector('.acct-stat-label').textContent.trim());
    });
    // ?show=late (from the main dashboard) opens that list straight away.
    var wanted = new URLSearchParams(window.location.search).get('show');
    if (wanted && groups[wanted]) {
        var tile = document.querySelector('.att-tile[data-show="' + wanted + '"]');
        show(wanted, tile ? tile.querySelector('.acct-stat-label').textContent.trim() : wanted);
    }
})();
</script>
<script>LiveRefresh.start(30000);</script>
<script>
(function () {
    var box = document.querySelector('[data-live="att-trend"]');
    var series = [
        ['on_time', @json(\App\Support\Ui::t('On Time')), '#22c55e', '#16a34a'],
        ['late', @json(\App\Support\Ui::t('Late')), '#fbbf24', '#d97706'],
        ['overtime', @json(\App\Support\Ui::t('Overtime')), '#818cf8', '#4f46e5'],
        ['leave', @json(\App\Support\Ui::t('On Leave')), '#7dd3fc', '#0284c7'],
        ['absent', @json(\App\Support\Ui::t('Absent')), '#fda4af', '#e11d48'],
    ];
    var rateLabel = @json(\App\Support\Ui::t('Attendance'));
    var data = JSON.parse(box.textContent);

    // Stacked: each bar is the whole staff that day, split by status.
    var chart = new Chart(document.getElementById('attendanceTrendChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: series.map(function (s) { return { key: s[0], label: s[1], data: data[s[0]], backgroundColor: s[2], hoverBackgroundColor: s[3], borderWidth: 0 }; }),
        },
        options: {
            maintainAspectRatio: false,
            animation: { duration: 300 },
            legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 14, fontColor: '#475569' } },
            tooltips: {
                mode: 'index', intersect: false,
                filter: function (item) { return item.yLabel > 0; },
                callbacks: {
                    footer: function (items) {
                        var r = items.length ? data.rate[items[0].index] : null;
                        return r === null ? '' : rateLabel + ': ' + r + '%';
                    },
                },
            },
            scales: {
                yAxes: [{
                    stacked: true,
                    ticks: { beginAtZero: true, precision: 0, fontColor: '#94a3b8' },
                    gridLines: { color: '#eef2f7', drawBorder: false, zeroLineColor: '#e2e8f0' },
                }],
                xAxes: [{
                    stacked: true,
                    barPercentage: 0.75,
                    categoryPercentage: 0.7,
                    ticks: { fontColor: '#64748b' },
                    gridLines: { display: false },
                }],
            },
        },
    });

    // The live refresh brings new numbers: redraw in place.
    document.addEventListener('live:updated', function (e) {
        if ((e.detail.keys || []).indexOf('att-trend') < 0) return;
        data = JSON.parse(box.textContent);
        chart.data.labels = data.labels;
        chart.data.datasets.forEach(function (ds) { ds.data = data[ds.key]; });
        chart.update();
    });
})();
</script>
@stop
