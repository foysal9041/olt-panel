@extends('adminlte::page')

@use('App\Services\GraphEngine')
@php
    $title = GraphEngine::TYPES[$type][0];
    $what = $port ? $device->name . ' · ' . $port->label : $device->name;
    $dataUrl = route('graph.data', ['type' => $type, 'id' => $subject->id]);
    $back = $port ? route('switches.ports.show', [$device, $port]) : route('switches.show', $device);
    $tz = config('app.timezone');
    $fmtInput = fn ($ts) => $ts ? \Illuminate\Support\Carbon::createFromTimestamp($ts, $tz)->format('Y-m-d\TH:i') : '';
@endphp

@section('title', $title . ' — ' . $what)

@section('content_header')
<x-noc.header :title="$title" icon="fas fa-chart-area" :back="$back"
    :subtitle="$what . ($port && $port->alias ? ' · ' . $port->alias : '') . ($port && $port->speed_label ? ' · ' . $port->speed_label : '')" />
@stop

@section('content')

{{-- The other graphs of the same port / device --}}
<ul class="nav nav-pills mb-3 flex-wrap" style="gap:.25rem">
    @foreach ($related as $r)
        <li class="nav-item">
            <a class="nav-link py-1 {{ $r === $type ? 'active' : '' }}" href="{{ route('graph.show', ['type' => $r, 'id' => $subject->id, 'graph' => $graph]) }}">{{ \App\Support\Ui::t(GraphEngine::TYPES[$r][0]) }}</a>
        </li>
    @endforeach
</ul>

<div class="card card-outline card-success">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-start mb-1" style="gap:.25rem .75rem">
            <div class="btn-group btn-group-sm flex-wrap mb-2" id="g-periods">
                @foreach (\App\Models\SwitchPortTraffic::GRAPHS as $key => [$label])
                    <button type="button" class="btn {{ ! $from && $key === $graph ? 'btn-success' : 'btn-outline-secondary' }}" data-graph="{{ $key }}">{{ \App\Support\Ui::t($label) }}</button>
                @endforeach
            </div>
            @include('switches._avg-toggle')
            <form class="form-inline mb-2" id="g-range" style="gap:.25rem">
                <label class="small text-muted mr-1">From</label>
                <input type="datetime-local" name="from" class="form-control form-control-sm" value="{{ $fmtInput($from) }}">
                <label class="small text-muted mx-1">To</label>
                <input type="datetime-local" name="to" class="form-control form-control-sm" value="{{ $fmtInput($to) }}">
                <button class="btn btn-sm btn-primary ml-1"><i class="fas fa-search"></i> Show</button>
            </form>
            <div class="ml-auto mb-2">
                <a href="#" class="btn btn-sm btn-outline-secondary" id="g-csv"><i class="fas fa-file-csv"></i> CSV</a>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="g-png"><i class="fas fa-image"></i> PNG</button>
            </div>
        </div>

        <div class="tg" id="g-main" data-zoom data-url="{{ $dataUrl }}" data-graph="{{ $graph }}" @if ($from && $to) data-from="{{ $from }}" data-to="{{ $to }}" @endif>
            <div class="tg-head"></div>
            <div class="tg-plot" style="height:380px"><canvas></canvas></div>
            <div class="tg-legend tg-wrap"></div>
        </div>
        <div class="small text-muted mt-2"><i class="fas fa-search-plus"></i> Drag across the graph to zoom in on that time. Up to 48 hours shows every minute; longer ranges use 30-minute, 2-hour or daily averages.</div>
    </div>
</div>

{{-- LibreNMS-style thumbnails: click one to show it above --}}
<div class="row">
    @foreach (['day' => '24 Hours', 'week' => 'Weekly', 'month' => 'Monthly', 'year' => 'Yearly'] as $key => $label)
        <div class="col-sm-6 col-xl-3">
            <div class="card g-thumb" role="button" data-graph="{{ $key }}" title="Show above">
                <div class="card-header py-1 small font-weight-bold">{{ \App\Support\Ui::t($label) }}</div>
                <div class="card-body p-2">
                    <div class="tg" data-compact data-points="150" data-url="{{ $dataUrl }}" data-graph="{{ $key }}">
                        <div class="tg-plot" style="height:110px"><canvas></canvas></div>
                        <div class="tg-legend"></div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

@stop

@section('css')
<style>.g-thumb { cursor: pointer; } .g-thumb:hover { box-shadow: 0 0 0 2px #16a34a; }</style>
@stop

@section('js')
@include('switches._traffic-assets')
<script>
(function () {
    var main = document.getElementById('g-main');
    var form = document.getElementById('g-range');
    var periods = document.querySelectorAll('#g-periods [data-graph]');

    function pad(n) { return n < 10 ? '0' + n : n; }
    function toInput(ts) { var d = new Date(ts * 1000); return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()); }
    function mark(key) { periods.forEach(function (b) { b.classList.toggle('btn-success', b.getAttribute('data-graph') === key); b.classList.toggle('btn-outline-secondary', b.getAttribute('data-graph') !== key); }); }
    function remember() {
        var u = new URL(window.location.href);
        ['graph', 'from', 'to'].forEach(function (k) { u.searchParams.delete(k); });
        if (main.getAttribute('data-from')) { u.searchParams.set('from', main.getAttribute('data-from')); u.searchParams.set('to', main.getAttribute('data-to')); }
        else u.searchParams.set('graph', main.getAttribute('data-graph'));
        history.replaceState(null, '', u.toString());
        document.getElementById('g-csv').href = TrafficGraph.csvUrl(main);
    }
    function range(from, to) {
        main.setAttribute('data-from', from); main.setAttribute('data-to', to);
        form.from.value = toInput(from); form.to.value = toInput(to);
        mark(null);
        TrafficGraph.load(main).then(remember);
    }

    main.onZoom = range;
    TrafficGraph.load(main).then(remember);
    TrafficGraph.lazy(Array.prototype.slice.call(document.querySelectorAll('.g-thumb .tg')));

    periods.forEach(function (b) {
        b.addEventListener('click', function () { mark(b.getAttribute('data-graph')); form.from.value = form.to.value = ''; TrafficGraph.load(main, b.getAttribute('data-graph')).then(remember); });
    });
    document.querySelectorAll('.g-thumb').forEach(function (c) {
        c.addEventListener('click', function () { mark(c.getAttribute('data-graph')); form.from.value = form.to.value = ''; TrafficGraph.load(main, c.getAttribute('data-graph')).then(remember); window.scrollTo({ top: 0, behavior: 'smooth' }); });
    });
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var f = Date.parse(form.from.value) / 1000, t = Date.parse(form.to.value) / 1000;
        if (!f || !t || f >= t) { alert(uiText('Pick a From time before the To time.', 'To-এর আগের একটি From সময় দিন।')); return; }
        range(Math.round(f), Math.round(t));
    });
    document.getElementById('g-png').addEventListener('click', function () { TrafficGraph.png(main, @json(\Illuminate\Support\Str::slug($what . ' ' . $title))); });
})();
</script>
@stop
