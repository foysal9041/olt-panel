@extends('adminlte::page')

@section('title', $target->name.' — Latency')

@section('content_header')
<x-noc.header :title="$target->name" :back="route('latency.index')"
    subtitle="{{ $target->host }}{{ $target->group ? ' · ' . $target->group : '' }} · {{ $target->pings }} pings every minute{{ $target->is_active ? '' : ' · paused' }}">
    <x-slot:badge>@include('latency.partials.status', ['status' => $target->status])</x-slot:badge>
    @can('access-latency-targets')
        <a href="{{ route('latency.targets.edit', $target) }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-edit"></i> Edit
        </a>
    @endcan
</x-noc.header>
@stop

@section('css')
<link rel="stylesheet" href="{{ asset('css/latency.css') }}?v={{ filemtime(public_path('css/latency.css')) }}">
@stop

@section('content')

@if ($target->alert_active)
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i> Over threshold since {{ $target->alert_since?->format('d M, h:i A') }}
        ({{ $target->alert_since?->diffForHumans() }})
    </div>
@endif

@if ($target->hasThresholds() || $target->description)
    <div class="card">
        <div class="card-body py-2 small d-flex flex-wrap align-items-center" style="gap: .25rem 1.5rem">
            @if ($target->hasThresholds())
                <span class="text-danger">
                    <i class="fas fa-bell"></i> Alert when
                    @if ($target->latency_threshold !== null) latency &gt; <strong>{{ $target->latency_threshold }} ms</strong> @endif
                    @if ($target->latency_threshold !== null && $target->loss_threshold !== null) or @endif
                    @if ($target->loss_threshold !== null) loss ≥ <strong>{{ $target->loss_threshold }}%</strong> @endif
                    @unless ($target->notify) <span class="text-muted">(Telegram muted)</span> @endunless
                </span>
            @endif
            @if ($target->description)
                <span class="text-muted"><i class="fas fa-info-circle"></i> {{ $target->description }}</span>
            @endif
        </div>
    </div>
@endif


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
