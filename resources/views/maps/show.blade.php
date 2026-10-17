@extends('adminlte::page')

@section('title', $map->name . ' — Weathermap')

@use('App\Models\WeathermapNode')
@use('App\Models\WeathermapLink')
@php
    $config = [
        'width' => $map->width,
        'height' => $map->height,
        'labels' => $map->label_mode,
        'refresh' => $map->refresh_seconds,
        'editing' => $editing,
        'dataUrl' => route('maps.data', $map),
        'positionsUrl' => route('maps.positions', $map),
        'csrf' => csrf_token(),
        'nodes' => $map->nodes->map(fn ($n) => [
            'id' => $n->id, 'label' => $n->label, 'x' => $n->x, 'y' => $n->y, 'state' => $n->state(),
            'glyph' => WeathermapNode::ICONS[$n->icon][1] ?? WeathermapNode::ICONS['switch'][1],
            'url' => $n->device && auth()->user()->canSeeSwitch($n->device) ? route('switches.show', $n->device) : null,
        ])->values(),
        'links' => $map->links->map(fn ($l) => [
            'id' => $l->id, 'from' => $l->from_node_id, 'to' => $l->to_node_id, 'label' => $l->label,
            'url' => $l->port ? route('switches.ports.show', [$l->port->network_switch_id, $l->port]) . '#traffic' : null,
        ])->values(),
    ];
    $nodeName = $map->nodes->pluck('label', 'id');
    $portOption = fn ($p, $sw) => $sw->name . ' · ' . $p->label . ($p->alias ? ' — ' . $p->alias : '') . ($p->speed_label ? ' (' . $p->speed_label . ')' : '');
@endphp

@section('content_header')
<x-noc.header :title="$map->name" icon="fas fa-project-diagram" :back="route('maps.index')"
    :subtitle="trim(($map->description ? $map->description . ' · ' : '') . $map->sharingLabel() . ' · by ' . ($map->owner->name ?? '—'))">
    <button type="button" class="btn btn-outline-secondary btn-sm" id="wm-full"><i class="fas fa-expand"></i> Full screen</button>
    @if ($canEdit)
        @if ($editing)
            <a href="{{ route('maps.show', $map) }}" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Done editing</a>
        @else
            <a href="{{ route('maps.show', [$map, 'edit' => 1]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-pen"></i> Edit map</a>
        @endif
    @endif
    @if ($canManage)
        <button type="button" class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#map-settings"><i class="fas fa-cog"></i> Settings &amp; sharing</button>
    @endif
</x-noc.header>
@stop

@section('css')
<style>
    .wm-wrap { background: #fff; border-radius: .5rem; box-shadow: 0 1px 3px rgba(15, 23, 42, .08); overflow: hidden; }
    .wm-wrap:fullscreen { display: flex; flex-direction: column; justify-content: center; background: #fff; padding: 1rem; }
    .wm-svg { display: block; width: 100%; height: auto; user-select: none; touch-action: none; }
    .wm-label { font: 600 12.5px 'Source Sans Pro', system-ui, sans-serif; fill: #0f172a; }
    .wm-icon { font: 900 13px 'Font Awesome 5 Free'; fill: #475569; }
    .wm-tag text { font: 600 10.5px 'Source Sans Pro', system-ui, sans-serif; fill: #0f172a; }
    .wm-drag { cursor: grab; }
    .wm-drag:active { cursor: grabbing; }
    .wm-link:hover polygon { stroke: #0f172a; stroke-width: 1.4; }
    .wm-legend { display: flex; flex-wrap: wrap; align-items: center; gap: .25rem .75rem; font-size: .78rem; color: #475569; }
    .wm-legend i { display: inline-block; width: 1.4rem; height: .7rem; margin-right: .25rem; border: 1px solid #94a3b8; border-radius: 2px; vertical-align: -1px; }
</style>
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

<div class="wm-wrap mb-2" id="wm-wrap">
    @if ($map->nodes->isEmpty())
        <div class="text-center text-muted py-5">
            <i class="fas fa-project-diagram fa-2x d-block mb-2"></i>
            Nothing on this map yet.
            @if ($canEdit && ! $editing)
                <a href="{{ route('maps.show', [$map, 'edit' => 1]) }}">Add nodes and links</a>
            @endif
        </div>
    @endif
</div>

<div class="d-flex flex-wrap align-items-center mb-3" style="gap:.5rem 1rem">
    <div class="wm-legend">
        <span class="font-weight-bold">Utilisation</span>
        @php $prev = 0; @endphp
        @foreach (WeathermapLink::SCALE as $upTo => $colour)
            <span><i style="background: {{ $colour }}"></i>{{ $prev }}–{{ $upTo }}%</span>
            @php $prev = $upTo; @endphp
        @endforeach
        <span><i style="background:#e5e7eb"></i>no data</span>
    </div>
    <span class="small text-muted ml-auto"><i class="fas fa-sync-alt"></i> Updated <span id="wm-updated">…</span></span>
    @if ($editing)<span id="wm-saved" class="small text-muted">Drag nodes to move them — positions save by themselves.</span>@endif
</div>

@if ($editing)
    <div class="row">
        {{-- Nodes --}}
        <div class="col-xl-5">
            <div class="card card-outline card-success">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-circle mr-1 text-success"></i> Nodes</h3></div>
                <form method="POST" action="{{ route('maps.nodes.store', $map) }}" class="card-body border-bottom">
                    @csrf
                    <div class="form-row">
                        <div class="col-md-12 form-group mb-2">
                            <label class="small font-weight-bold mb-1">Switch / router <small class="text-muted">(or leave blank for a label such as "IIG")</small></label>
                            <select name="network_switch_id" class="form-control js-select2" data-placeholder="— A label, not a device —" data-allow-clear="true">
                                <option value=""></option>
                                @foreach ($devices as $d)
                                    <option value="{{ $d->id }}">{{ $d->isRouter() ? 'Router · ' : '' }}{{ $d->name }} ({{ $d->ip }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-7 form-group mb-2">
                            <label class="small font-weight-bold mb-1">Name <small class="text-muted">(blank = device name)</small></label>
                            <input type="text" name="label" maxlength="60" class="form-control form-control-sm" placeholder="e.g. IIG Summit">
                        </div>
                        <div class="col-5 form-group mb-2">
                            <label class="small font-weight-bold mb-1">Icon</label>
                            <select name="icon" class="form-control form-control-sm">
                                <option value="">Automatic</option>
                                @foreach (WeathermapNode::ICONS as $key => [$label])<option value="{{ $key }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <button class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Add node</button>
                </form>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        @forelse ($map->nodes as $n)
                            <tr>
                                <td class="pl-3">
                                    <strong>{{ $n->label }}</strong>
                                    <div class="small text-muted">{{ $n->device ? $n->device->typeLabel() . ' · ' . $n->device->ip : 'Label' }} · {{ WeathermapNode::ICONS[$n->icon][0] ?? $n->icon }}</div>
                                    <div class="collapse mt-2" id="node-{{ $n->id }}">
                                        <form method="POST" action="{{ route('maps.nodes.update', [$map, $n]) }}" class="form-row">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="network_switch_id" value="{{ $n->network_switch_id }}">
                                            <div class="col-7 mb-1"><input type="text" name="label" maxlength="60" class="form-control form-control-sm" value="{{ $n->label }}"></div>
                                            <div class="col-5 mb-1">
                                                <select name="icon" class="form-control form-control-sm">
                                                    @foreach (WeathermapNode::ICONS as $key => [$label])<option value="{{ $key }}" @selected($n->icon === $key)>{{ $label }}</option>@endforeach
                                                </select>
                                            </div>
                                            <div class="col-12"><button class="btn btn-primary btn-xs"><i class="fas fa-check"></i> Save</button></div>
                                        </form>
                                    </div>
                                </td>
                                <td class="text-right text-nowrap pr-3">
                                    <button type="button" class="btn btn-link btn-sm p-0" data-toggle="collapse" data-target="#node-{{ $n->id }}" title="Edit"><i class="fas fa-pen"></i></button>
                                    <form method="POST" action="{{ route('maps.nodes.destroy', [$map, $n]) }}" class="d-inline js-confirm-delete" data-confirm-message="Remove {{ $n->label }} and its links from the map?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-link btn-sm p-0 ml-2 text-danger" title="Remove"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted small py-3">No nodes yet.</td></tr>
                        @endforelse
                    </table>
                </div>
            </div>
        </div>

        {{-- Links --}}
        <div class="col-xl-7">
            <div class="card card-outline card-primary">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-arrows-alt-h mr-1 text-primary"></i> Links</h3></div>
                @if ($map->nodes->count() < 2)
                    <div class="card-body small text-muted">Add at least two nodes, then join them here.</div>
                @else
                    <form method="POST" action="{{ route('maps.links.store', $map) }}" class="card-body border-bottom">
                        @csrf
                        <div class="form-row">
                            <div class="col-6 form-group mb-2">
                                <label class="small font-weight-bold mb-1">From</label>
                                <select name="from_node_id" class="form-control form-control-sm">@foreach ($map->nodes as $n)<option value="{{ $n->id }}">{{ $n->label }}</option>@endforeach</select>
                            </div>
                            <div class="col-6 form-group mb-2">
                                <label class="small font-weight-bold mb-1">To</label>
                                <select name="to_node_id" class="form-control form-control-sm">@foreach ($map->nodes as $n)<option value="{{ $n->id }}" @selected($loop->index === 1)>{{ $n->label }}</option>@endforeach</select>
                            </div>
                            <div class="col-12 form-group mb-2">
                                <label class="small font-weight-bold mb-1">Interface whose traffic colours the link</label>
                                @include('maps._port-select', ['selected' => null])
                            </div>
                            <div class="col-md-4 form-group mb-2">
                                <label class="small font-weight-bold mb-1">Capacity <small class="text-muted">(Mbps, blank = port speed)</small></label>
                                <input type="number" name="bandwidth_mbps" min="1" class="form-control form-control-sm" placeholder="e.g. 10000">
                            </div>
                            <div class="col-md-5 form-group mb-2">
                                <label class="small font-weight-bold mb-1">Label <small class="text-muted">(optional)</small></label>
                                <input type="text" name="label" maxlength="60" class="form-control form-control-sm" placeholder="e.g. Summit 10G">
                            </div>
                            <div class="col-md-3 form-group mb-2 d-flex align-items-end">
                                <div class="custom-control custom-checkbox small">
                                    <input type="checkbox" class="custom-control-input" id="rev-new" name="reverse" value="1">
                                    <label class="custom-control-label" for="rev-new">Reverse</label>
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add link</button>
                        <small class="text-muted ml-2">The interface's <em>out</em> traffic is drawn From → To. An interface on the "To" device is turned round by itself; tick Reverse to turn it by hand.</small>
                    </form>
                @endif
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        @forelse ($map->links as $l)
                            <tr>
                                <td class="pl-3">
                                    <strong>{{ $nodeName[$l->from_node_id] ?? '?' }}</strong> <i class="fas fa-arrows-alt-h text-muted"></i> <strong>{{ $nodeName[$l->to_node_id] ?? '?' }}</strong>
                                    @if ($l->label)<span class="text-muted">· {{ $l->label }}</span>@endif
                                    <div class="small text-muted">
                                        {{ $l->port ? $l->port->networkSwitch?->name . ' · ' . $l->port->label : 'No interface' }}{{ $l->reverse ? ' · reversed' : '' }}{{ $l->bandwidth_mbps ? ' · capacity ' . $l->bandwidth_mbps . ' Mbps' : '' }}
                                    </div>
                                    <div class="collapse mt-2" id="link-{{ $l->id }}">
                                        <form method="POST" action="{{ route('maps.links.update', [$map, $l]) }}" class="form-row">
                                            @csrf @method('PUT')
                                            <div class="col-6 mb-1"><select name="from_node_id" class="form-control form-control-sm">@foreach ($map->nodes as $n)<option value="{{ $n->id }}" @selected($n->id === $l->from_node_id)>{{ $n->label }}</option>@endforeach</select></div>
                                            <div class="col-6 mb-1"><select name="to_node_id" class="form-control form-control-sm">@foreach ($map->nodes as $n)<option value="{{ $n->id }}" @selected($n->id === $l->to_node_id)>{{ $n->label }}</option>@endforeach</select></div>
                                            <div class="col-12 mb-1">@include('maps._port-select', ['selected' => $l->switch_port_id])</div>
                                            <div class="col-4 mb-1"><input type="number" name="bandwidth_mbps" min="1" class="form-control form-control-sm" value="{{ $l->bandwidth_mbps }}" placeholder="Mbps"></div>
                                            <div class="col-5 mb-1"><input type="text" name="label" maxlength="60" class="form-control form-control-sm" value="{{ $l->label }}" placeholder="Label"></div>
                                            <div class="col-3 mb-1 d-flex align-items-center">
                                                <input type="hidden" name="reverse" value="0">
                                                <div class="custom-control custom-checkbox small">
                                                    <input type="checkbox" class="custom-control-input" id="rev-{{ $l->id }}" name="reverse" value="1" @checked($l->reverse)>
                                                    <label class="custom-control-label" for="rev-{{ $l->id }}">Reverse</label>
                                                </div>
                                            </div>
                                            <div class="col-12"><button class="btn btn-primary btn-xs"><i class="fas fa-check"></i> Save</button></div>
                                        </form>
                                    </div>
                                </td>
                                <td class="text-right text-nowrap pr-3">
                                    <button type="button" class="btn btn-link btn-sm p-0" data-toggle="collapse" data-target="#link-{{ $l->id }}" title="Edit"><i class="fas fa-pen"></i></button>
                                    <form method="POST" action="{{ route('maps.links.destroy', [$map, $l]) }}" class="d-inline js-confirm-delete" data-confirm-message="Remove this link?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-link btn-sm p-0 ml-2 text-danger" title="Remove"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted small py-3">No links yet.</td></tr>
                        @endforelse
                    </table>
                </div>
            </div>
        </div>
    </div>
@endif

@if ($canManage)
    @include('maps._settings')
@endif

@stop

@section('js')
<script src="{{ asset('js/weathermap.js') }}?v={{ filemtime(public_path('js/weathermap.js')) }}"></script>
<script>
(function () {
    var cfg = @json($config);
    var wrap = document.getElementById('wm-wrap');
    if (cfg.nodes.length) Weathermap.start(wrap, cfg);

    document.getElementById('wm-full').addEventListener('click', function () {
        if (document.fullscreenElement) document.exitFullscreen(); else if (wrap.requestFullscreen) wrap.requestFullscreen();
    });

    $('.js-select2').each(function () {
        $(this).select2({ theme: 'bootstrap4', width: '100%', allowClear: !!$(this).data('allow-clear'), placeholder: $(this).data('placeholder') || '' });
    });
    document.querySelectorAll('.js-vis').forEach(function (r) {
        r.addEventListener('change', function () {
            var t = document.querySelector('.js-roles');
            if (t) t.hidden = document.querySelector('.js-vis:checked').value !== 'roles';
        });
    });
    @if (session('error'))
        $('#map-settings').modal('show');
    @endif
})();
</script>
@stop
