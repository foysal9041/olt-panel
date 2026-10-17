@extends('adminlte::page')

@section('title', 'Port graphs — ' . $switch->name)

@use('App\Services\GraphEngine')
@php
    // Traffic through the device: what enters its physical ports (a port-channel's members already carry its traffic).
    $physical = $ports->filter(fn ($p) => $p->if_type !== 161 && $p->hasCurrentTraffic());
    $thumbs = ['day' => '24 Hours', 'week' => 'Weekly', 'month' => 'Monthly', 'year' => 'Yearly'];
@endphp

@section('content_header')
<x-noc.header :title="'Port graphs — ' . $switch->name" :back="route('switches.show', $switch)"
    subtitle="{{ $switch->ip }} · every port, busiest first — Day / Week / Month / Year" />
@stop

@section('content')

<div class="card card-outline card-success">
    <div class="card-body d-flex flex-wrap align-items-center" style="gap:.5rem 1.25rem">
        <div class="btn-group btn-group-sm flex-wrap mb-2" role="group">
            @foreach (GraphEngine::PORT_GRAPHS as $gt)
                <a href="{{ request()->fullUrlWithQuery(['type' => $gt]) }}" class="btn {{ $type === $gt ? 'btn-success' : 'btn-outline-secondary' }}">{{ \App\Support\Ui::t(GraphEngine::TYPES[$gt][0]) }}</a>
            @endforeach
        </div>
        @include('switches._avg-toggle')
        <div class="small mb-2">
            <span class="text-muted">Through the switch now:</span>
            <span class="text-success ml-1"><i class="fas fa-arrow-down"></i> In <strong>{{ \App\Models\SwitchPort::formatBps($physical->sum('in_bps')) }}</strong></span>
            <span class="text-primary ml-2"><i class="fas fa-arrow-up"></i> Out <strong>{{ \App\Models\SwitchPort::formatBps($physical->sum('out_bps')) }}</strong></span>
        </div>
    </div>
</div>

@if ($ports->isEmpty())
    <div class="card"><div class="card-body text-center text-muted py-5">
        <i class="fas fa-chart-area fa-2x mb-2 d-block"></i>
        No traffic counters from this switch yet — they are read on every poll (each minute).
    </div></div>
@else
    @foreach ($ports as $port)
        @php $isUp = $port->oper_status === \App\Models\SwitchPort::UP; @endphp
        <div class="card mb-3" @if (! $isUp) style="opacity:.7" @endif>
            <div class="card-header py-2 d-flex align-items-center flex-wrap" style="gap:.25rem .5rem">
                <a href="{{ route('switches.ports.show', [$switch, $port]) }}#traffic" class="text-reset mr-auto">
                    <strong>{{ $port->label }}</strong>
                    @if ($port->alias)<span class="text-muted small">{{ $port->alias }}</span>@endif
                </a>
                @if ($port->hasCurrentTraffic())
                    <span class="small"><span class="text-success"><i class="fas fa-arrow-down"></i> {{ \App\Models\SwitchPort::formatBps($port->in_bps) }}</span>
                        <span class="text-primary ml-1"><i class="fas fa-arrow-up"></i> {{ \App\Models\SwitchPort::formatBps($port->out_bps) }}</span></span>
                @endif
                @if ($port->speed_label)<span class="badge badge-light border">{{ $port->speed_label }}</span>@endif
                <span class="badge {{ $isUp ? 'badge-success' : 'badge-danger' }}">{{ $isUp ? 'UP' : 'DOWN' }}</span>
            </div>
            <div class="card-body py-2">
                <div class="row">
                    @foreach ($thumbs as $key => $label)
                        <div class="col-6 col-lg-3">
                            <a href="{{ route('graph.show', ['type' => $type, 'id' => $port->id, 'graph' => $key]) }}" class="pg-thumb d-block text-reset">
                                <div class="small text-muted">{{ \App\Support\Ui::t($label) }}</div>
                                <div class="tg" data-compact data-points="120" data-url="{{ route('graph.data', ['type' => $type, 'id' => $port->id]) }}" data-graph="{{ $key }}">
                                    <div class="tg-plot" style="height:90px"><canvas></canvas></div>
                                    <div class="tg-legend"></div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
@endif

@stop

@section('js')
@include('switches._traffic-assets')
<style>
    .pg-thumb { padding: .25rem; border-radius: .4rem; }
    .pg-thumb:hover { background: #f8fafc; box-shadow: 0 0 0 1px #cbd5e1; }
    .pg-thumb .tg-legend { font-size: .7rem; }
</style>
<script>
TrafficGraph.lazy(Array.prototype.slice.call(document.querySelectorAll('.tg')));
</script>
@stop
