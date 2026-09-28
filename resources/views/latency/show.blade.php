@extends('adminlte::page')

@section('title', $target->name.' — Latency')

@section('content_header')
<div class="d-flex justify-content-between align-items-start flex-wrap">
    <div>
        <h1 class="mb-1">
            {{ $target->name }}
            @include('latency.partials.status', ['status' => $target->status])
        </h1>
        <div class="text-muted">
            <code>{{ $target->host }}</code>
            @if ($target->group)
                &middot; {{ $target->group }}
            @endif
            &middot; {{ $target->pings }} pings every minute
            @if ($target->hasThresholds())
                &middot; <span class="text-danger"><i class="fas fa-bell"></i> alert
                    @if ($target->latency_threshold !== null) &gt; {{ $target->latency_threshold }} ms @endif
                    @if ($target->latency_threshold !== null && $target->loss_threshold !== null) or @endif
                    @if ($target->loss_threshold !== null) ≥ {{ $target->loss_threshold }}% loss @endif
                </span>
            @endif
            @unless ($target->is_active)
                &middot; <span class="text-danger">paused</span>
            @endunless
        </div>
        @if ($target->description)
            <div class="text-muted small mt-1">{{ $target->description }}</div>
        @endif
        @if ($target->alert_active)
            <div class="alert alert-danger py-1 px-2 mt-2 mb-0 d-inline-block">
                <i class="fas fa-exclamation-triangle"></i> Over threshold since {{ $target->alert_since?->format('d M, h:i A') }}
                ({{ $target->alert_since?->diffForHumans() }})
            </div>
        @endif
    </div>
    <div class="mt-2 mt-md-0">
        <a href="{{ route('latency.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        @can('access-latency-targets')
            <a href="{{ route('latency.targets.edit', $target) }}" class="btn btn-warning btn-sm">
                <i class="fas fa-edit"></i> Edit
            </a>
        @endcan
    </div>
</div>
@stop

@section('css')
<link rel="stylesheet" href="{{ asset('css/latency.css') }}?v={{ filemtime(public_path('css/latency.css')) }}">
@stop

@section('content')

@foreach ($ranges as $key => $range)
    <div class="card card-outline card-primary">
        <div class="card-header">
            <h3 class="card-title">{{ $range['label'] }}</h3>
        </div>
        <div class="card-body">
            <div class="js-smokegraph"
                 data-url="{{ route('latency.data', ['target' => $target, 'range' => $key, 'points' => 400]) }}"
                 data-refresh="{{ $range['seconds'] <= 30 * 3600 ? 60 : 300 }}"></div>
        </div>
    </div>
@endforeach

@stop

@section('js')
<script src="{{ asset('js/smokegraph.js') }}?v={{ filemtime(public_path('js/smokegraph.js')) }}"></script>
<script>
document.querySelectorAll('.js-smokegraph').forEach(function (el) {
    SmokeGraph.create(el, { url: el.dataset.url, height: 250, refresh: +el.dataset.refresh });
});
</script>
@stop
