@extends('adminlte::page')

@section('title', 'Dashboard')

@section('css')
<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v={{ filemtime(public_path('css/dashboard.css')) }}">
@stop

@php
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $pct = fn ($part, $whole) => $whole > 0 ? round($part / $whole * 100) : 0;
    $dangerCount = collect($issues)->where(0, 'danger')->count();
    $anyModule = in_array(true, $can, true);
    $eventColors = ['danger' => '#ef4444', 'success' => '#22c55e', 'warning' => '#f59e0b', 'info' => '#0ea5e9'];
@endphp

@section('content')

<div class="dash-hero">
    <div class="row align-items-center">
        <div class="col-sm-8">
            <h1>{{ $greeting }}, {{ auth()->user()->name }} 👋</h1>
            <div class="dash-hero-sub">Sunlit Network ERP — here's what's happening across your network today.</div>

            @if ($anyModule)
                <a href="#needs-attention" class="dash-health {{ $issues ? 'dash-health--bad' : '' }}">
                    <span class="dash-health-dot"></span>
                    @if (! $issues)
                        All systems normal
                    @else
                        {{ count($issues) }} {{ Str::plural('item', count($issues)) }} need attention
                        @if ($dangerCount) · {{ $dangerCount }} critical @endif
                    @endif
                </a>
            @endif

            @php
                $chips = array_filter([
                    $olt ? ['fas fa-network-wired', $olt['total'] . ' ' . Str::plural('OLT', $olt['total'])] : null,
                    $switches ? ['fas fa-server', $switches['total'] . ' ' . Str::plural('switch', $switches['total'])] : null,
                    $nttn ? ['fas fa-project-diagram', $nttn['total'] . ' NTTN ' . Str::plural('link', $nttn['total'])] : null,
                    $ip ? ['fas fa-globe', $ip['blocks']->count() . ' IP ' . Str::plural('block', $ip['blocks']->count())] : null,
                    $latency ? ['fas fa-wave-square', $latency['total'] . ' latency ' . Str::plural('target', $latency['total'])] : null,
                ]);
            @endphp
            @if ($chips)
                <div class="dash-chips">
                    @foreach ($chips as [$icon, $text])
                        <span><i class="{{ $icon }}"></i> {{ $text }}</span>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="col-sm-4">
            <div class="dash-clock">
                <span id="dash-clock">{{ now()->format('h:i A') }}</span>
                <small>{{ now()->format('l, d F Y') }}</small>
            </div>
        </div>
    </div>
</div>

@unless ($anyModule)

    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        No modules have been assigned to your account yet. Contact your administrator to get access.
    </div>

@else

@if ($actions)
    <div class="qa-grid">
        @foreach ($actions as [$label, $icon, $url, $tone])
            <a href="{{ $url }}" class="qa">
                <span class="qa-icon tone-{{ $tone }}"><i class="{{ $icon }}"></i></span>
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </div>
@endif

{{-- ================= KPI tiles ================= --}}
<div class="kpi-grid">

    @if ($olt)
        <a href="{{ route('olt.dashboard') }}" class="kpi">
            <div class="kpi-top">
                <span class="kpi-label">OLTs Online</span>
                <span class="kpi-icon tone-indigo"><i class="fas fa-network-wired"></i></span>
            </div>
            <div class="kpi-value">{{ $olt['online'] }} <small>/ {{ $olt['total'] }}</small></div>
            <div class="kpi-foot">
                @if ($olt['offline']) <span class="text-danger font-weight-bold">{{ $olt['offline'] }} offline</span>
                @else All online @endif
            </div>
            <div class="kpi-bar"><span class="{{ $olt['offline'] ? 'fill-amber' : 'fill-green' }}" style="width: {{ $pct($olt['online'], $olt['total']) }}%"></span></div>
        </a>
    @endif

    @if ($switches)
        <a href="{{ route('switches.index') }}" class="kpi">
            <div class="kpi-top">
                <span class="kpi-label">Switches Up</span>
                <span class="kpi-icon tone-sky"><i class="fas fa-server"></i></span>
            </div>
            <div class="kpi-value">{{ $switches['up'] }} <small>/ {{ $switches['total'] }}</small></div>
            <div class="kpi-foot">
                {{ $switches['ports_up'] }} / {{ $switches['ports'] }} ports up
                @if ($switches['rx_alarms']) · <span class="text-warning font-weight-bold">{{ $switches['rx_alarms'] }} low Rx</span> @endif
            </div>
            <div class="kpi-bar"><span class="{{ $switches['down'] ? 'fill-rose' : 'fill-sky' }}" style="width: {{ $pct($switches['up'], $switches['total']) }}%"></span></div>
        </a>
    @endif

    @if ($nttn)
        <a href="{{ route('nttn-links.index') }}" class="kpi">
            <div class="kpi-top">
                <span class="kpi-label">NTTN Links Up</span>
                <span class="kpi-icon tone-violet"><i class="fas fa-project-diagram"></i></span>
            </div>
            <div class="kpi-value">{{ $nttn['up'] }} <small>/ {{ $nttn['monitored'] }}</small></div>
            <div class="kpi-foot">
                @if ($nttn['down']) <span class="text-danger font-weight-bold">{{ $nttn['down'] }} down</span> ·
                @endif
                @if ($nttn['no_ip']) {{ $nttn['no_ip'] }} without IP
                @elseif (! $nttn['down']) All links up @endif
            </div>
            <div class="kpi-bar"><span class="{{ $nttn['down'] ? 'fill-rose' : 'fill-indigo' }}" style="width: {{ $pct($nttn['up'], $nttn['monitored']) }}%"></span></div>
        </a>
    @endif

    @if ($ip && $ip['size'])
        <a href="{{ route('ip-pools.index') }}" class="kpi">
            <div class="kpi-top">
                <span class="kpi-label">Public IP Used</span>
                <span class="kpi-icon tone-green"><i class="fas fa-globe"></i></span>
            </div>
            <div class="kpi-value">{{ $pct($ip['used'], $ip['size']) }}<small>%</small></div>
            <div class="kpi-foot">{{ number_format($ip['size'] - $ip['used']) }} of {{ number_format($ip['size']) }} IPs free · {{ $ip['subnets'] }} subnets</div>
            <div class="kpi-bar"><span class="{{ $pct($ip['used'], $ip['size']) >= 90 ? 'fill-rose' : ($pct($ip['used'], $ip['size']) >= 75 ? 'fill-amber' : 'fill-green') }}" style="width: {{ $pct($ip['used'], $ip['size']) }}%"></span></div>
        </a>
    @endif

    @if ($latency)
        <a href="{{ route('latency.index') }}" class="kpi">
            <div class="kpi-top">
                <span class="kpi-label">Latency Targets OK</span>
                <span class="kpi-icon tone-violet"><i class="fas fa-wave-square"></i></span>
            </div>
            <div class="kpi-value">{{ $latency['ok'] }} <small>/ {{ $latency['total'] }}</small></div>
            <div class="kpi-foot">
                @php $bad = $latency['total'] - $latency['ok']; @endphp
                @if ($bad) <span class="text-danger font-weight-bold">{{ $bad }} over threshold / down</span>
                @else All within limits @endif
            </div>
            <div class="kpi-bar"><span class="{{ $bad ? 'fill-amber' : 'fill-indigo' }}" style="width: {{ $pct($latency['ok'], $latency['total']) }}%"></span></div>
        </a>
    @endif

    @if ($attendance)
        @php $in = $attendance['present'] + $attendance['late']; @endphp
        <a href="{{ route('attendance.dashboard') }}" class="kpi">
            <div class="kpi-top">
                <span class="kpi-label">In Office Today</span>
                <span class="kpi-icon tone-green"><i class="fas fa-fingerprint"></i></span>
            </div>
            <div class="kpi-value">{{ $in }} <small>/ {{ $attendance['total'] }}</small></div>
            <div class="kpi-foot">{{ $attendance['late'] }} late · {{ $attendance['absent'] }} absent</div>
            <div class="kpi-bar"><span class="fill-green" style="width: {{ $pct($in, $attendance['total']) }}%"></span></div>
        </a>
    @endif

    @if ($accounts)
        <a href="{{ route('accounts.dashboard') }}" class="kpi">
            <div class="kpi-top">
                <span class="kpi-label">Collected ({{ now()->format('M') }})</span>
                <span class="kpi-icon tone-amber"><i class="fas fa-file-invoice-dollar"></i></span>
            </div>
            <div class="kpi-value money" style="font-size: 1.5rem">&#2547;{{ number_format($accounts['collected']) }}</div>
            <div class="kpi-foot">of &#2547;{{ number_format($accounts['invoiced']) }} invoiced · {{ $accounts['unpaid'] }} unpaid</div>
            <div class="kpi-bar"><span class="fill-amber" style="width: {{ $pct($accounts['collected'], $accounts['invoiced']) }}%"></span></div>
        </a>
    @endif

</div>

{{-- ================= Needs attention + activity ================= --}}
<div class="row">

    @if ($olt || $switches || $latency)
        <div class="{{ $switches ? 'col-lg-7' : 'col-12' }}">
            <div class="card dash-panel" id="needs-attention">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-bell mr-1 text-danger"></i> Needs Attention</h3>
                    @if ($issues)
                        <span class="badge {{ $dangerCount ? 'badge-danger' : 'badge-warning' }}">{{ count($issues) }}</span>
                    @endif
                </div>
                <div class="card-body p-0" style="max-height: 420px; overflow-y: auto;">
                    @forelse ($issues as [$severity, $icon, $title, $detail, $url])
                        <a href="{{ $url }}" class="issue">
                            <span class="issue-icon {{ $severity === 'danger' ? 'tone-rose' : 'tone-amber' }}"><i class="{{ $icon }}"></i></span>
                            <span>
                                <div class="issue-title">{{ $title }}</div>
                                @if ($detail) <div class="issue-detail">{{ $detail }}</div> @endif
                            </span>
                        </a>
                    @empty
                        <div class="dash-empty">
                            <i class="fas fa-check-circle"></i>
                            <strong>Everything looks good.</strong><br>
                            No offline devices, down links or threshold alerts right now.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    @if ($switches)
        <div class="col-lg-5">
            <div class="card dash-panel">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-history mr-1 text-primary"></i> Recent Activity</h3>
                    <a href="{{ route('switch-events.index') }}" class="small">View all</a>
                </div>
                <div class="card-body p-0">
                    @if ($events->isEmpty())
                        <div class="dash-empty"><i class="fas fa-stream" style="color:#cbd5e1"></i>No port or switch events yet.</div>
                    @else
                        <ul class="timeline-mini">
                            @foreach ($events as $event)
                                @php [$label, $color] = \App\Models\SwitchEvent::TYPES[$event->type] ?? [$event->type, 'secondary']; @endphp
                                <li style="--dot: {{ $eventColors[$color] ?? '#94a3b8' }}">
                                    <div class="tl-time">{{ $event->occurred_at->format('d M, h:i A') }} · {{ $event->occurred_at->diffForHumans() }}</div>
                                    <div class="tl-text"><strong>{{ $label }}</strong> — {{ $event->message }}</div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    @endif

</div>

{{-- ================= Module panels ================= --}}
<div class="row">

    @if ($latency && $latency['targets']->isNotEmpty())
        <div class="col-lg-4 col-md-6">
            <div class="card dash-panel">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-wave-square mr-1" style="color:#7c3aed"></i> Latency</h3>
                    <a href="{{ route('latency.index') }}" class="small">Graphs</a>
                </div>
                <div class="card-body p-0">
                    @php $dotColors = ['up' => '#22c55e', 'degraded' => '#f59e0b', 'alert' => '#ef4444', 'down' => '#ef4444', 'unknown' => '#cbd5e1']; @endphp
                    @foreach ($latency['targets']->take(8) as $t)
                        <a href="{{ route('latency.show', $t) }}" class="lat-row">
                            <span class="lat-dot" style="background: {{ $dotColors[$t->status] }}"></span>
                            <span class="lat-name">
                                {{ $t->name }}
                                <div class="lat-host">{{ $t->host }}</div>
                            </span>
                            <span class="lat-ms {{ $t->alert_active ? 'text-danger' : '' }}">
                                {{ $t->last_median !== null ? round($t->last_median, 1) : '—' }} <small>ms</small>
                                @if ($t->last_loss > 0)
                                    <div class="small text-warning text-right">{{ round($t->last_loss) }}% loss</div>
                                @endif
                            </span>
                        </a>
                    @endforeach
                    @if ($latency['targets']->count() > 8)
                        <a href="{{ route('latency.index') }}" class="lat-row justify-content-center small">+ {{ $latency['targets']->count() - 8 }} more</a>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($attendance)
        <div class="col-lg-4 col-md-6">
            <div class="card dash-panel">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-fingerprint mr-1 text-success"></i> Attendance Today</h3>
                    <a href="{{ route('attendance.dashboard') }}" class="small">Open</a>
                </div>
                <div class="card-body">
                    @php
                        $segments = [
                            ['Present', $attendance['present'], '#22c55e'],
                            ['Late', $attendance['late'], '#f59e0b'],
                            ['On leave', $attendance['leave'] ?? 0, '#0ea5e9'],
                            ['Absent', $attendance['absent'], '#f43f5e'],
                        ];
                    @endphp
                    <div class="stack-bar">
                        @foreach ($segments as [$label, $value, $color])
                            @if ($value)
                                <span style="width: {{ $pct($value, $attendance['total']) }}%; background: {{ $color }}" title="{{ $label }}: {{ $value }}"></span>
                            @endif
                        @endforeach
                    </div>
                    <div class="stack-legend">
                        @foreach ($segments as [$label, $value, $color])
                            <div><i style="background: {{ $color }}"></i> {{ $label }} <b>{{ $value }}</b></div>
                        @endforeach
                    </div>
                    <div class="small text-muted mt-3">{{ $attendance['total'] }} employees on devices</div>
                </div>
            </div>
        </div>
    @endif

    @if ($accounts)
        <div class="col-lg-4 col-md-6">
            <div class="card dash-panel">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-file-invoice-dollar mr-1 text-warning"></i> Billing — {{ now()->format('F') }}</h3>
                    <a href="{{ route('accounts.dashboard') }}" class="small">Open</a>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-1 small text-muted">
                        <span>Collected</span>
                        <span>{{ $pct($accounts['collected'], $accounts['invoiced']) }}%</span>
                    </div>
                    <div class="stack-bar mb-3">
                        <span style="width: {{ $pct($accounts['collected'], $accounts['invoiced']) }}%; background: #22c55e"></span>
                    </div>
                    <div class="stack-legend">
                        <div><i style="background:#6366f1"></i> Invoiced <b class="money">&#2547;{{ number_format($accounts['invoiced']) }}</b></div>
                        <div><i style="background:#22c55e"></i> Collected <b class="money">&#2547;{{ number_format($accounts['collected']) }}</b></div>
                        <div><i style="background:#f43f5e"></i> Outstanding <b class="money">&#2547;{{ number_format($accounts['outstanding']) }}</b></div>
                        <div><i style="background:#94a3b8"></i> Invoices <b>{{ $accounts['count'] }}</b></div>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>

{{-- ================= Network resources ================= --}}
@if (($ip && $ip['blocks']->isNotEmpty()) || $nttn)
<div class="row">

    @if ($ip && $ip['blocks']->isNotEmpty())
        <div class="{{ $nttn ? 'col-lg-7' : 'col-12' }}">
            <div class="card dash-panel">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-globe mr-1 text-success"></i> IP Space</h3>
                    <a href="{{ route('ip-pools.index') }}" class="small">IP Management</a>
                </div>
                <div class="card-body">
                    @foreach ($ip['blocks'] as $block)
                        @php
                            $bUsed = $block->usedCount($block->allocations);
                            $bPct = $pct($bUsed, $block->size());
                        @endphp
                        <a href="{{ route('ip-blocks.show', $block) }}" class="ipblock-row">
                            <div class="d-flex justify-content-between align-items-baseline">
                                <span><strong class="mono">{{ $block->cidr }}</strong> <span class="text-muted small">{{ $block->name }}</span></span>
                                <span class="small"><strong>{{ $bPct }}%</strong> <span class="text-muted">· {{ $block->size() - $bUsed }} free</span></span>
                            </div>
                            <div class="stack-bar mt-1">
                                <span style="width: {{ $bPct }}%; background: {{ $bPct >= 90 ? '#f43f5e' : ($bPct >= 75 ? '#f59e0b' : '#22c55e') }}"></span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if ($nttn)
        <div class="{{ $ip && $ip['blocks']->isNotEmpty() ? 'col-lg-5' : 'col-12' }}">
            <div class="card dash-panel">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-project-diagram mr-1" style="color:#7c3aed"></i> NTTN Links</h3>
                    <a href="{{ route('nttn-links.index') }}" class="small">All links</a>
                </div>
                <div class="card-body">
                    @php
                        $segments = [
                            ['Up', $nttn['up'], '#22c55e'],
                            ['Down', $nttn['down'], '#f43f5e'],
                            ['Checking', $nttn['monitored'] - $nttn['up'] - $nttn['down'], '#94a3b8'],
                            ['No IP', $nttn['no_ip'], '#e2e8f0'],
                        ];
                    @endphp
                    <div class="stack-bar">
                        @foreach ($segments as [$label, $value, $color])
                            @if ($value > 0)
                                <span style="width: {{ $pct($value, $nttn['total']) }}%; background: {{ $color }}" title="{{ $label }}: {{ $value }}"></span>
                            @endif
                        @endforeach
                    </div>
                    <div class="stack-legend">
                        @foreach ($segments as [$label, $value, $color])
                            <div><i style="background: {{ $color }}"></i> {{ $label }} <b>{{ $value }}</b></div>
                        @endforeach
                    </div>
                    @if ($nttn['no_ip'])
                        <div class="small text-muted mt-3">
                            <i class="fas fa-info-circle"></i> {{ $nttn['no_ip'] }} {{ Str::plural('link', $nttn['no_ip']) }} have no Peering/Ping IP yet, so they aren't monitored.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

</div>
@endif

@endunless

@stop

@section('js')
<script>
(function () {
    var el = document.getElementById('dash-clock');
    function tick() {
        var d = new Date();
        var h = d.getHours(), m = d.getMinutes(), s = d.getSeconds();
        var ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        el.textContent = (h < 10 ? '0' + h : h) + ':' + (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s) + ' ' + ampm;
    }
    tick();
    setInterval(tick, 1000);

    // Fresh numbers every minute (pollers run every minute too).
    setTimeout(function () { location.reload(); }, 60000);
})();
</script>
@stop
