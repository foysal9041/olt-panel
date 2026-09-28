@extends('adminlte::page')

@section('title', 'Attendance Dashboard')

@php
    $in = $summary['present'] + $summary['late'];
    $expected = max(0, $summary['total'] - ($summary['leave'] ?? 0));
    $rate = $expected > 0 ? round($in / $expected * 100) : 0;
    $onlineDevices = $devices->filter->isOnline()->count();
    $statusMeta = [
        'present' => ['Present', 'success'],
        'late' => ['Late', 'warning'],
        'leave' => ['On Leave', 'info'],
        'absent' => ['Absent', 'danger'],
        'weekend' => ['Weekly Off', 'secondary'],
        'upcoming' => ['—', 'secondary'],
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
</style>
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

{{-- ============ Today ============ --}}
<div class="acct-stats">

    <div class="acct-stat" style="--accent:#4f46e5">
        <div class="acct-stat-label">In Office <i class="fas fa-user-check"></i></div>
        <div class="acct-stat-value">{{ $in }} <small class="text-muted" style="font-size:1rem">/ {{ $expected }}</small></div>
        <div class="acct-stat-foot">{{ $rate }}% attendance today</div>
        <div class="acct-progress"><span style="width: {{ $rate }}%"></span></div>
    </div>

    <div class="acct-stat" style="--accent:#16a34a">
        <div class="acct-stat-label">On Time <i class="fas fa-check"></i></div>
        <div class="acct-stat-value">{{ $summary['present'] }}</div>
        <div class="acct-stat-foot">Checked in before the grace time</div>
    </div>

    <div class="acct-stat" style="--accent:#d97706">
        <div class="acct-stat-label">Late <i class="fas fa-clock"></i></div>
        <div class="acct-stat-value">{{ $summary['late'] }}</div>
        <div class="acct-stat-foot">After shift start + grace</div>
    </div>

    <div class="acct-stat" style="--accent:#e11d48">
        <div class="acct-stat-label">Absent <i class="fas fa-user-times"></i></div>
        <div class="acct-stat-value">{{ $summary['absent'] }}</div>
        <div class="acct-stat-foot">No punch yet today</div>
    </div>

    <div class="acct-stat" style="--accent:#0284c7">
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

@if($unmappedCount > 0)
    <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap">
        <span>
            <i class="fas fa-exclamation-triangle mr-1"></i>
            <strong>{{ $unmappedCount }} device {{ $unmappedCount === 1 ? 'PIN' : 'PINs' }}</strong> punched but aren't linked to any employee — their attendance isn't counted.
        </span>
        <a href="#unmapped" class="btn btn-sm btn-dark mt-1 mt-md-0">Review</a>
    </div>
@endif

<div class="row">

    {{-- ============ Today's board ============ --}}
    <div class="col-lg-8">
        <div class="card acct-panel">
            <div class="card-header">
                <h3 class="card-title">Today's Attendance</h3>
                <span class="text-muted small">{{ $board->count() }} employees</span>
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
                    <tbody>
                        @forelse ($board as $row)
                            @php [$label, $color] = $statusMeta[$row['status']] ?? [ucfirst($row['status']), 'secondary']; @endphp
                            <tr>
                                <td class="pl-3">
                                    <div class="d-flex align-items-center">
                                        <span class="acct-avatar mr-2">{{ mb_substr($row['employee']->name, 0, 2) }}</span>
                                        <div>
                                            <div class="font-weight-bold" style="color:#0f172a">{{ $row['employee']->name }}</div>
                                            <div class="small text-muted">
                                                {{ $row['employee']->designation ?: 'Employee' }}
                                                @if ($row['employee']->dutyShift) · {{ $row['employee']->dutyShift->name }} @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge badge-{{ $color }}">{{ $label }}</span></td>
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

        <div class="card acct-panel">
            <div class="card-header">
                <h3 class="card-title">Last 7 Days</h3>
                @can('access-attendance-report')
                    <a href="{{ route('attendance.report') }}" class="small">Full report</a>
                @endcan
            </div>
            <div class="card-body">
                <div style="position: relative; height: 240px;">
                    <canvas id="attendanceTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ Side column ============ --}}
    <div class="col-lg-4">

        <div class="card acct-panel">
            <div class="card-header">
                <h3 class="card-title">Devices</h3>
                <span class="small {{ $onlineDevices < $devices->count() ? 'text-danger' : 'text-success' }}">
                    {{ $onlineDevices }} / {{ $devices->count() }} online
                </span>
            </div>
            <div class="card-body p-0">
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

        @if ($pendingLeaves->isNotEmpty())
            <div class="card acct-panel">
                <div class="card-header">
                    <h3 class="card-title">Pending Leave Requests</h3>
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
                                    {{ $leave->leaveType->name ?? 'Leave' }} ·
                                    {{ $leave->start_date->format('d M') }}@if (! $leave->start_date->equalTo($leave->end_date)) – {{ $leave->end_date->format('d M') }}@endif
                                </div>
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="card acct-panel">
            <div class="card-header">
                <h3 class="card-title">Recent Punches</h3>
            </div>
            <div class="card-body p-0" style="max-height: 380px; overflow-y: auto;">
                @forelse($recentPunches as $punch)
                    <div class="acct-list-row">
                        <span class="acct-avatar" style="background:#f1f5f9;color:#475569"><i class="fas fa-fingerprint"></i></span>
                        <span class="acct-list-main">
                            <div class="acct-list-title">{{ $punch->employee->name ?? 'Unknown' }}</div>
                            <div class="acct-list-sub">{{ $punch->zkDevice->name ?? $punch->zkDevice->serial_number ?? '-' }}</div>
                        </span>
                        <span class="text-right small">
                            <div class="font-weight-bold att-time" style="color:#0f172a">{{ $punch->punched_at->format('h:i A') }}</div>
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

@if($unmappedCount > 0)
    <div class="card acct-panel" id="unmapped">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-exclamation-triangle text-warning mr-1"></i>
                Unlinked Device PINs
            </h3>
            <span class="text-muted small">Punches from these PINs aren't counted until they're mapped</span>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th class="pl-3">PIN (Device User ID)</th>
                        <th>Device</th>
                        <th>Punches</th>
                        <th>Last Seen</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($unmappedPunches as $punch)
                        <tr>
                            <td class="pl-3"><code>{{ $punch->device_user_id }}</code></td>
                            <td>{{ $punch->zkDevice->name ?? $punch->zkDevice->serial_number ?? 'Unknown device' }}</td>
                            <td>{{ $punch->punches_count }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($punch->last_punched_at)->format('d M, h:i A') }}</td>
                            <td class="text-right pr-3">
                                @can('access-attendance-employees')
                                    <a href="{{ route('attendance.employees.create', ['device_user_id' => $punch->device_user_id]) }}"
                                       class="btn btn-xs btn-warning">
                                        Map to Employee
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@stop

@section('js')
<script>
new Chart(document.getElementById('attendanceTrendChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: @json(array_map(fn ($day) => \Illuminate\Support\Carbon::parse($day['date'])->format('D d'), $trend)),
        datasets: [
            { label: 'Present', data: @json(array_column($trend, 'present')), backgroundColor: '#22c55e', hoverBackgroundColor: '#16a34a' },
            { label: 'Absent', data: @json(array_column($trend, 'absent')), backgroundColor: '#fb7185', hoverBackgroundColor: '#e11d48' },
        ],
    },
    options: {
        maintainAspectRatio: false,
        legend: { display: true, position: 'bottom', labels: { boxWidth: 12, fontColor: '#475569' } },
        tooltips: { mode: 'index', intersect: false },
        scales: {
            yAxes: [{
                ticks: { beginAtZero: true, precision: 0, fontColor: '#94a3b8' },
                gridLines: { color: '#eef2f7', drawBorder: false },
            }],
            xAxes: [{
                barPercentage: 0.7,
                categoryPercentage: 0.6,
                ticks: { fontColor: '#64748b' },
                gridLines: { display: false },
            }],
        },
    },
});

// Punches arrive from the devices continuously; refresh once a minute.
setTimeout(function () { location.reload(); }, 60000);
</script>
@stop
