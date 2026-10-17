@extends('adminlte::page')

@section('title', $dashboard->name . ' — Graph Dashboard')

@use('App\Models\SwitchPort')
@php
    $graphs = \App\Models\SwitchPortTraffic::GRAPHS;
    $cols = [12 => 'col-12', 6 => 'col-lg-6', 4 => 'col-md-6 col-xl-4'];
    $hasTop = $widgets->contains('kind', 'top');
@endphp

@section('content_header')
<x-noc.header title="Graph Dashboards" icon="fas fa-chart-area"
    :subtitle="trim(($dashboard->description ? $dashboard->description . ' · ' : '') . $dashboard->sharingLabel() . ' · by ' . ($dashboard->owner->name ?? '—'))" />
@stop

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif

{{-- LibreNMS-style bar: pick a dashboard, edit / delete it, make a new one --}}
<div class="d-flex flex-wrap align-items-center mb-2" style="gap:.5rem 1rem">
    <div class="input-group input-group-sm lw-bar">
        <div class="input-group-prepend"><span class="input-group-text">Dashboards</span></div>
        <select class="form-control" id="dash-pick" aria-label="Dashboard">
            @foreach ($allDashboards as $d)
                <option value="{{ route('graphs.show', $d) }}" @selected($d->id === $dashboard->id)>{{ $d->name }}</option>
            @endforeach
        </select>
        <div class="input-group-append">
            @if ($canEdit)
                @if ($editing)
                    <a href="{{ route('graphs.show', $dashboard) }}" class="btn btn-success" title="Done editing"><i class="fas fa-check"></i></a>
                @else
                    <a href="{{ route('graphs.show', [$dashboard, 'edit' => 1]) }}" class="btn btn-outline-secondary" title="Edit graphs"><i class="fas fa-edit"></i></a>
                @endif
            @endif
            @if ($canManage)
                <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#dash-settings" title="Settings &amp; sharing"><i class="fas fa-cog"></i></button>
                <button type="submit" form="dash-delete" class="btn btn-danger" title="Delete dashboard"><i class="fas fa-trash-alt"></i></button>
            @endif
            @unless (auth()->user()->isViewer())
                <button type="button" class="btn btn-success" data-toggle="modal" data-target="#dash-new" title="New dashboard"><i class="fas fa-plus"></i></button>
            @endunless
        </div>
    </div>
    <a href="{{ route('graphs.index', ['list' => 1]) }}" class="small text-muted">All dashboards</a>
</div>

<div class="d-flex flex-wrap align-items-center mb-3" style="gap:.5rem 1rem">
    <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Period">
        <a href="{{ request()->fullUrlWithQuery(['graph' => null]) }}" class="btn {{ $graph ? 'btn-outline-secondary' : 'btn-success' }}">Each graph's own</a>
        @foreach ($graphs as $key => [$title])
            <a href="{{ request()->fullUrlWithQuery(['graph' => $key]) }}" class="btn {{ $graph === $key ? 'btn-success' : 'btn-outline-secondary' }}">{{ \App\Support\Ui::t($title) }}</a>
        @endforeach
    </div>
    @include('switches._avg-toggle')
    <span class="small text-muted"><i class="fas fa-sync-alt"></i> Refreshes every {{ $dashboard->refresh_seconds < 60 ? $dashboard->refresh_seconds . 's' : ($dashboard->refresh_seconds / 60) . ' min' }}</span>
    @if ($editing)
        <button type="button" class="btn btn-success btn-sm ml-auto" data-toggle="collapse" data-target="#add-graph" aria-expanded="{{ $widgets->isEmpty() || $errors->any() ? 'true' : 'false' }}">
            <i class="fas fa-plus"></i> Add graph
        </button>
    @endif
</div>

@if ($editing)
    <div class="collapse {{ $widgets->isEmpty() || $errors->any() ? 'show' : '' }}" id="add-graph">
        <div class="card card-outline card-success">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-plus-circle mr-1 text-success"></i> Add a graph</h3></div>
            <div class="card-body">
                @include('graphs._widget-form', ['widget' => null])
            </div>
        </div>
    </div>
@endif

@if ($widgets->isEmpty())
    <div class="card">
        <div class="card-body text-center text-muted py-5">
            <i class="fas fa-chart-area fa-2x d-block mb-2"></i>
            No graphs on this dashboard yet.
            @if ($canEdit && ! $editing)
                <a href="{{ route('graphs.show', [$dashboard, 'edit' => 1]) }}">Add some</a>.
            @endif
        </div>
    </div>
@else
    <div class="row">
        @foreach ($widgets as $widget)
            @php
                $ports = $widget->portList;
                $small = $widget->width === 4;
            @endphp
            <div class="{{ $cols[$widget->width] ?? 'col-lg-6' }}">
                <div class="lw-card">
                    <div class="lw-head">
                        @php $one = $widget->kind === 'traffic' && $ports->count() === 1 ? $ports->first() : null; @endphp
                        @if ($one)
                            <a href="{{ route('graph.show', ['type' => 'port_bits', 'id' => $one->id]) }}" class="lw-title" title="{{ $one->networkSwitch?->name }} · {{ $one->label }}{{ $one->speed_label ? ' · ' . $one->speed_label : '' }}">{{ $widget->displayTitle($ports) }}</a>
                        @else
                            <span class="lw-title">{{ $widget->displayTitle($ports) }}</span>
                        @endif
                        @if ($editing)
                            <div class="btn-group btn-group-sm lw-tools">
                                @foreach (['up' => 'fa-arrow-up', 'down' => 'fa-arrow-down'] as $dir => $icon)
                                    <form method="POST" action="{{ route('graphs.widgets.move', [$dashboard, $widget]) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="dir" value="{{ $dir }}">
                                        <button class="btn btn-light btn-sm" title="Move {{ $dir }}" @disabled(($dir === 'up' && $loop->parent->first) || ($dir === 'down' && $loop->parent->last))><i class="fas {{ $icon }}"></i></button>
                                    </form>
                                @endforeach
                                <button type="button" class="btn btn-light btn-sm" data-toggle="collapse" data-target="#edit-{{ $widget->id }}" title="Edit"><i class="fas fa-pen"></i></button>
                                <form method="POST" action="{{ route('graphs.widgets.destroy', [$dashboard, $widget]) }}" class="d-inline js-confirm-delete" data-confirm-message="Remove this graph from the dashboard?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-light btn-sm text-danger" title="Remove"><i class="fas fa-trash-alt"></i></button>
                                </form>
                            </div>
                        @endif
                    </div>

                    @if ($editing)
                        <div class="collapse" id="edit-{{ $widget->id }}">
                            <div class="p-2 border-bottom bg-light">
                                @include('graphs._widget-form', ['widget' => $widget])
                            </div>
                        </div>
                    @endif

                    <div class="lw-body">
                        @if ($widget->kind === 'cpu')
                            @if (! $widget->cpuDevice)
                                <div class="text-muted small py-4 text-center">This device no longer exists.</div>
                            @else
                                <div class="tg" data-style="rrd" data-points="{{ $widget->width === 12 ? 720 : 360 }}"
                                     data-url="{{ route('graphs.widgets.data', [$dashboard, $widget]) }}" data-graph="{{ $graph ?? ($widget->graph ?: $dashboard->default_graph) }}">
                                    <div class="tg-head"></div>
                                    <div class="tg-plot"><canvas></canvas></div>
                                    <div class="tg-legend tg-wrap"></div>
                                </div>
                            @endif
                        @elseif ($widget->kind === 'traffic')
                            @if ($ports->isEmpty())
                                <div class="text-muted small py-4 text-center">The interfaces on this graph no longer exist.</div>
                            @else
                                <div class="tg" data-style="rrd" @if ($widget->mirror()) data-mirror @endif data-points="{{ $widget->width === 12 ? 720 : 360 }}"
                                     data-url="{{ route('graphs.widgets.data', [$dashboard, $widget]) }}" data-graph="{{ $graph ?? ($widget->graph ?: $dashboard->default_graph) }}">
                                    <div class="tg-head"></div>
                                    <div class="tg-plot"><canvas></canvas></div>
                                    <div class="tg-legend tg-wrap"></div>
                                </div>
                                @if ($ports->count() > 1)
                                    <details class="small text-muted mt-1">
                                        <summary>{{ $ports->count() }} interfaces added up</summary>
                                        @foreach ($ports as $p)
                                            <div><a href="{{ route('switches.ports.show', [$p->network_switch_id, $p]) }}#traffic" class="text-reset">{{ $p->networkSwitch?->name }} · {{ $p->label }}</a>{{ $p->alias ? ' — ' . $p->alias : '' }}</div>
                                        @endforeach
                                    </details>
                                @endif
                            @endif
                        @else
                            <div data-live="w-{{ $widget->id }}">
                                @if ($widget->topPorts->isEmpty())
                                    <div class="text-muted small py-4 text-center">No traffic readings right now.</div>
                                @else
                                    <table class="table table-sm table-hover mb-0 small">
                                        <thead><tr><th>#</th><th>Interface</th><th class="text-right">In</th><th class="text-right">Out</th><th style="width:5rem"></th></tr></thead>
                                        <tbody>
                                            @foreach ($widget->topPorts as $p)
                                                @php $u = $p->utilisation(); @endphp
                                                <tr>
                                                    <td class="text-muted">{{ $loop->iteration }}</td>
                                                    <td>
                                                        <a href="{{ route('switches.ports.show', [$p->network_switch_id, $p]) }}#traffic" class="text-reset"><strong>{{ $p->label }}</strong></a>
                                                        <span class="text-muted">{{ $p->networkSwitch?->name }}{{ $p->alias ? ' · ' . $p->alias : '' }}</span>
                                                    </td>
                                                    <td class="text-right text-success text-nowrap">{{ SwitchPort::formatBps($p->in_bps) }}</td>
                                                    <td class="text-right text-primary text-nowrap">{{ SwitchPort::formatBps($p->out_bps) }}</td>
                                                    <td>
                                                        @if ($u !== null)
                                                            <div class="progress" style="height:4px" title="{{ number_format($u, 1) }}% of {{ $p->speed_label }}">
                                                                <div class="progress-bar {{ $u >= 90 ? 'bg-danger' : ($u >= 70 ? 'bg-warning' : 'bg-success') }}" style="width: {{ max(2, $u) }}%"></div>
                                                            </div>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

@if ($canManage)
    @include('graphs._settings')
@endif

@unless (auth()->user()->isViewer())
    <div class="modal fade" id="dash-new" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="POST" action="{{ route('graphs.store') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle mr-1 text-success"></i> New dashboard</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" maxlength="100" class="form-control" placeholder="e.g. Core uplinks" required>
                    </div>
                    <div class="form-group mb-0">
                        <label>Description <small class="text-muted">(optional)</small></label>
                        <input type="text" name="description" maxlength="255" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button class="btn btn-success"><i class="fas fa-plus"></i> Make dashboard</button>
                </div>
            </form>
        </div>
    </div>
@endunless

@stop

@section('css')
<style>
    .lw-bar { width: auto; max-width: 100%; }
    .lw-bar select { min-width: 12rem; }
    .lw-card { background: #fff; border: 1px solid #dee2e6; margin-bottom: 1rem; }
    .lw-head { position: relative; display: flex; align-items: center; justify-content: center; min-height: 2.4rem; padding: .35rem 2.5rem; background: #e9ecef; border-bottom: 1px solid #dee2e6; }
    .lw-title { font-size: .95rem; color: #222; text-align: center; }
    a.lw-title:hover { color: #0d6efd; text-decoration: none; }
    .lw-tools { position: absolute; right: .35rem; }
    .lw-body { padding: .5rem .6rem .6rem; }
    .lw-body .tg[data-style="rrd"] .tg-plot { height: 170px; }
    .rrd-legend { border-collapse: collapse; margin-top: .3rem; }
    .rrd-legend th, .rrd-legend td { padding: 0 .9rem 0 0; font-weight: normal; text-align: left; white-space: nowrap; }
    .tg[data-style="rrd"] .tg-legend { font-family: 'DejaVu Sans Mono', Menlo, Consolas, monospace; font-size: 11px; color: #222; line-height: 1.35; overflow-x: auto; }
    .tg[data-style="rrd"] .tg-key { border-radius: 0; width: .65rem; height: .65rem; }
</style>
@stop

@section('js')
@include('switches._traffic-assets')
<script>
(function () {
    var override = @json($graph);
    var graphs = Array.prototype.slice.call(document.querySelectorAll('.tg'));
    TrafficGraph.lazy(graphs, override);

    // Keep the graphs current; skip while the tab is hidden.
    setInterval(function () {
        if (document.hidden) return;
        graphs.forEach(function (el) { if (el._data) TrafficGraph.load(el); });
    }, {{ max(30, $dashboard->refresh_seconds) * 1000 }});

    // Dashboard picker.
    var pick = document.getElementById('dash-pick');
    if (pick) pick.addEventListener('change', function () { window.location = pick.value; });

    // Add / edit form: interface picker and the fields each type uses.
    $('.js-select2').select2({ theme: 'bootstrap4', width: '100%' });
    document.querySelectorAll('.js-widget-form').forEach(function (form) {
        var kind = form.querySelector('.js-kind');
        function sync() {
            form.querySelectorAll('.js-for-traffic').forEach(function (el) { el.hidden = kind.value !== 'traffic'; });
            form.querySelectorAll('.js-for-top').forEach(function (el) { el.hidden = kind.value !== 'top'; });
            form.querySelectorAll('.js-for-cpu').forEach(function (el) { el.hidden = kind.value !== 'cpu'; });
            form.querySelectorAll('.js-for-graph').forEach(function (el) { el.hidden = kind.value === 'top'; });
        }
        kind.addEventListener('change', sync);
        sync();
    });

    // Settings: the roles table only for "Chosen roles".
    document.querySelectorAll('.js-vis').forEach(function (r) {
        r.addEventListener('change', function () {
            var t = document.querySelector('.js-roles');
            if (t) t.hidden = document.querySelector('.js-vis:checked').value !== 'roles';
        });
    });
    @if ($errors->has('name') || session('error'))
        $('#dash-settings').modal('show');
    @endif
})();
</script>
@if ($hasTop)
    <script>LiveRefresh.start({{ max(30, $dashboard->refresh_seconds) * 1000 }});</script>
@endif
@stop
