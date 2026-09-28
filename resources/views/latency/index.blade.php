@extends('adminlte::page')

@section('title', 'Latency Graphs')

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <h1>Latency Graphs</h1>
    @can('access-latency-targets')
        <a href="{{ route('latency.targets.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Target
        </a>
    @endcan
</div>
@stop

@section('css')
<link rel="stylesheet" href="{{ asset('css/latency.css') }}?v={{ filemtime(public_path('css/latency.css')) }}">
@stop

@section('content')

@if ($targets->contains(fn ($t) => $t->hasThresholds() && $t->notify))
    @include('partials.telegram-off-banner')
@endif

<div class="row">
    @foreach ([
        ['Total Targets', $targets->count(), 'bg-info', 'fas fa-bullseye'],
        ['Up', $counts['up'] ?? 0, 'bg-success', 'fas fa-check'],
        ['Packet Loss', $counts['degraded'] ?? 0, 'bg-warning', 'fas fa-exclamation-triangle'],
        ['Over Threshold / Down', ($counts['alert'] ?? 0) + ($counts['down'] ?? 0), 'bg-danger', 'fas fa-times'],
    ] as [$label, $value, $bg, $icon])
        <div class="col-lg-3 col-6">
            <div class="info-box">
                <span class="info-box-icon {{ $bg }}"><i class="{{ $icon }}"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ $label }}</span>
                    <span class="info-box-number">{{ $value }}</span>
                </div>
            </div>
        </div>
    @endforeach
</div>

@if ($groups->isNotEmpty())
    <div class="latency-groups mb-2">
        <a href="{{ route('latency.index') }}"
           class="btn btn-sm {{ request('group') ? 'btn-outline-secondary' : 'btn-primary' }}">All</a>
        @foreach ($groups as $group)
            <a href="{{ route('latency.index', ['group' => $group]) }}"
               class="btn btn-sm {{ request('group') === $group ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $group }}</a>
        @endforeach
    </div>
@endif

@forelse ($targets->groupBy(fn ($t) => $t->group ?: 'Ungrouped') as $group => $groupTargets)

    @if ($groups->isNotEmpty())
        <div class="latency-group-title">{{ $group }}</div>
    @endif

    <div class="row">
        @foreach ($groupTargets as $target)
            <div class="col-xl-4 col-lg-6">
                <div class="card latency-card {{ $target->alert_active ? 'latency-card--alert' : '' }}">
                    <div class="card-header">
                        <div>
                            <div class="latency-card-title">
                                <a href="{{ route('latency.show', $target) }}">{{ $target->name }}</a>
                            </div>
                            <div class="latency-card-host">{{ $target->host }}</div>
                        </div>
                        @include('latency.partials.status', ['status' => $target->status])
                    </div>

                    <div class="latency-card-metrics">
                        <div>Median<b>{{ $target->last_median !== null ? round($target->last_median, 2).' ms' : '—' }}</b></div>
                        <div>Loss<b>{{ $target->last_loss !== null ? round($target->last_loss, 1).'%' : '—' }}</b></div>
                        @if ($target->latency_threshold !== null)
                            <div>Threshold<b class="font-weight-normal" style="font-size:.95rem">{{ $target->latency_threshold }} ms</b></div>
                        @endif
                        <div class="ml-auto text-right">Last probe<b class="font-weight-normal" style="font-size:.8rem">
                            {{ $target->last_probed_at?->diffForHumans() ?? 'never' }}</b></div>
                    </div>

                    <div class="card-body pt-2 pb-2 px-2">
                        <a href="{{ route('latency.show', $target) }}" class="d-block text-reset" title="Open full graphs">
                            <div class="js-smokegraph"
                                 data-url="{{ route('latency.data', ['target' => $target, 'range' => '3h', 'points' => 120]) }}"></div>
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

@empty

    <div class="card">
        <div class="card-body text-center text-muted py-5">
            <i class="fas fa-wave-square fa-3x mb-3 d-block text-light"></i>
            No latency targets yet.
            @can('access-latency-targets')
                <div class="mt-3">
                    <a href="{{ route('latency.targets.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add your first target
                    </a>
                </div>
            @endcan
        </div>
    </div>

@endforelse

@stop

@section('js')
<script src="{{ asset('js/smokegraph.js') }}?v={{ filemtime(public_path('js/smokegraph.js')) }}"></script>
<script>
document.querySelectorAll('.js-smokegraph').forEach(function (el) {
    SmokeGraph.create(el, { url: el.dataset.url, height: 130, compact: true, refresh: 60 });
});

// Status badges and numbers come from the server; refresh them now and then.
setTimeout(function () { location.reload(); }, 5 * 60 * 1000);
</script>
@stop
