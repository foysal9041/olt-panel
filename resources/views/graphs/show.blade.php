@extends('adminlte::page')

@section('title', $dashboard->name . ' — Graph Dashboard')

@use('App\Models\SwitchPort')
@php
    $graphs = \App\Models\SwitchPortTraffic::GRAPHS;
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

@if ($editing && $widgets->isNotEmpty())
    <div class="lw-hint mb-3">
        <i class="fas fa-arrows-alt mr-1"></i>
        Drag a graph by its title bar to move it, and its bottom-right corner to make it wider, narrower, taller or shorter. Changes save by themselves.
    </div>
@endif

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
    <div class="lw-grid {{ $editing ? 'is-editing' : '' }}" id="lw-grid" data-layout-url="{{ route('graphs.layout', $dashboard) }}">
        @foreach ($widgets as $widget)
            @php
                $ports = $widget->portList;
                $one = $widget->kind === 'traffic' && $ports->count() === 1 ? $ports->first() : null;
                $periodKey = $graph ?? ($widget->graph ?: $dashboard->default_graph);
                $sub = match (true) {
                    $one !== null => trim($one->networkSwitch?->name . ' · ' . $one->label . ($one->speed_label ? ' · ' . $one->speed_label : '')),
                    $widget->kind === 'traffic' && $ports->count() > 1 => $ports->map(fn ($p) => $p->alias ?: $p->label)->take(3)->implode(' + ') . ($ports->count() > 3 ? ' + ' . ($ports->count() - 3) . ' ' . \App\Support\Ui::t('more') : ''),
                    default => null,
                };
            @endphp
            <div class="lw-item" data-id="{{ $widget->id }}" data-w="{{ $widget->width }}" data-h="{{ $widget->plotHeight() }}"
                 style="--w: {{ $widget->width }}; --wm: {{ $widget->mediumWidth() }}; --h: {{ $widget->plotHeight() }}px">
                <div class="lw-card">
                    <div class="lw-head" @if ($editing) title="{{ \App\Support\Ui::t('Drag to move') }}" @endif>
                        @if ($editing)
                            <span class="lw-grip"><i class="fas fa-grip-vertical"></i></span>
                        @endif
                        <div class="lw-titles">
                            @if ($one && ! $editing)
                                <a href="{{ route('graph.show', ['type' => 'port_bits', 'id' => $one->id]) }}" class="lw-title">{{ $widget->displayTitle($ports) }}</a>
                            @else
                                <span class="lw-title">{{ $widget->displayTitle($ports) }}</span>
                            @endif
                            @if ($sub)
                                <div class="lw-sub" title="{{ $sub }}">{{ $sub }}</div>
                            @endif
                        </div>
                        @if ($widget->kind === 'top')
                            <span class="lw-period">{{ \App\Support\Ui::t('Now') }}</span>
                        @else
                            <span class="lw-period">{{ \App\Support\Ui::t($graphs[$periodKey][0] ?? '24 Hours') }}</span>
                        @endif
                        @if ($editing)
                            <div class="btn-group btn-group-sm lw-tools">
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
                                <div class="tg" data-style="rrd" data-points="{{ $widget->width >= 8 ? 720 : 360 }}"
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
                                <div class="tg" data-style="rrd" @if ($widget->mirror()) data-mirror @endif data-points="{{ $widget->width >= 8 ? 720 : 360 }}"
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
                    @if ($editing)
                        <span class="lw-resize" title="{{ \App\Support\Ui::t('Drag to resize') }}"></span>
                    @endif
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
    /* 12-column grid: each graph spans its width; two to a row on tablets, one on phones */
    .lw-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 1rem; align-items: start; margin-bottom: 1rem; }
    .lw-item { grid-column: span var(--w, 6); min-width: 0; }
    @media (max-width: 1199.98px) { .lw-item { grid-column: span var(--wm, 6); } }
    @media (max-width: 767.98px) { .lw-item { grid-column: span 12; } }
    .lw-card { position: relative; background: #fff; border: 1px solid #d8dde4; border-radius: 4px; box-shadow: 0 1px 2px rgba(15, 23, 42, .05); }
    .lw-head { display: flex; align-items: center; gap: .5rem; min-height: 2.6rem; padding: .3rem .55rem .3rem .7rem; background: #f3f5f8; border-bottom: 1px solid #e2e6eb; border-radius: 4px 4px 0 0; }
    .lw-titles { flex: 1; min-width: 0; line-height: 1.2; }
    .lw-title { display: block; font-size: .92rem; font-weight: 600; color: #1f2937; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    a.lw-title:hover { color: #0d6efd; text-decoration: none; }
    .lw-sub { font-size: .72rem; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .lw-period { flex: none; padding: .05rem .5rem; border: 1px solid #dfe3e8; border-radius: 999px; background: #fff; font-size: .68rem; font-weight: 700; color: #475569; white-space: nowrap; }
    .lw-tools { flex: none; }
    .lw-body { padding: .45rem .55rem .55rem; }
    .lw-body .tg[data-style="rrd"] .tg-plot { height: var(--h, 200px); }
    .lw-hint { padding: .45rem .75rem; border: 1px dashed #86efac; border-radius: 4px; background: #f0fdf4; font-size: .85rem; color: #166534; }

    /* Arranging: drag by the title bar, resize by the corner */
    .is-editing .lw-card { outline: 1px dashed #94a3b8; outline-offset: 2px; }
    .is-editing .lw-head { cursor: grab; touch-action: none; user-select: none; }
    .lw-grip { flex: none; color: #94a3b8; }
    .lw-resize { position: absolute; right: 1px; bottom: 1px; width: 16px; height: 16px; cursor: nwse-resize; touch-action: none; z-index: 2;
        background: linear-gradient(135deg, transparent 0 55%, #94a3b8 55% 62%, transparent 62% 72%, #94a3b8 72% 79%, transparent 79% 89%, #94a3b8 89%); }
    .lw-item.is-dragging { position: fixed; z-index: 1060; pointer-events: none; opacity: .92; }
    .lw-item.is-dragging .lw-card { box-shadow: 0 14px 34px rgba(15, 23, 42, .28); cursor: grabbing; }
    .lw-placeholder { border: 2px dashed #16a34a; border-radius: 4px; background: rgba(22, 163, 74, .06); }
    .lw-size-tip { position: absolute; right: 20px; bottom: 3px; z-index: 3; padding: 0 .4rem; border-radius: 3px; background: #0f172a; color: #fff; font-size: .7rem; font-variant-numeric: tabular-nums; }
    .lw-toast { position: fixed; right: 1rem; bottom: 4.2rem; z-index: 1070; padding: .4rem .8rem; border-radius: 4px; background: #166534; color: #fff; font-size: .85rem; box-shadow: 0 6px 18px rgba(15, 23, 42, .2); opacity: 0; transition: opacity .2s; pointer-events: none; }
    .lw-toast.is-error { background: #b91c1c; }
    .lw-toast.is-on { opacity: 1; }
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

    // Arranging (edit mode): drag a graph by its title bar, resize it by its corner; saved by itself.
    (function () {
        var grid = document.getElementById('lw-grid');
        if (!grid || !grid.classList.contains('is-editing')) return;
        var token = (document.querySelector('meta[name="csrf-token"]') || {}).content;
        var MIN = {{ \App\Models\GraphWidget::MIN_HEIGHT }}, MAX = {{ \App\Models\GraphWidget::MAX_HEIGHT }};
        var toastEl = document.createElement('div'), toastTimer, saveTimer;
        toastEl.className = 'lw-toast';
        document.body.appendChild(toastEl);

        function toast(text, error) {
            toastEl.textContent = text;
            toastEl.classList.toggle('is-error', !!error);
            toastEl.classList.add('is-on');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(function () { toastEl.classList.remove('is-on'); }, error ? 6000 : 1800);
        }
        function save() {
            clearTimeout(saveTimer);
            saveTimer = setTimeout(function () {
                var items = Array.prototype.map.call(grid.querySelectorAll('.lw-item[data-id]'), function (it) {
                    return { id: +it.getAttribute('data-id'), width: +it.getAttribute('data-w'), height: +it.getAttribute('data-h') };
                });
                fetch(grid.getAttribute('data-layout-url'), {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ items: items }),
                }).then(function (r) {
                    if (!r.ok) throw new Error(r.status);
                    toast(uiText('Layout saved', 'সাজানো সেভ হয়েছে'));
                }).catch(function () {
                    toast(uiText('Could not save the layout — reload the page and try again.', 'সাজানো সেভ করা যায়নি — পেজ রিলোড করে আবার চেষ্টা করুন।'), true);
                });
            }, 250);
        }
        function setSize(it, w, h) {
            it.setAttribute('data-w', w);
            it.setAttribute('data-h', h);
            it.style.setProperty('--w', w);
            it.style.setProperty('--wm', w > 6 ? 12 : 6);
            it.style.setProperty('--h', h + 'px');
        }
        function drag(e, onMove, onEnd) {
            e.preventDefault();
            function move(ev) { onMove(ev); }
            function up(ev) {
                document.removeEventListener('pointermove', move);
                document.removeEventListener('pointerup', up);
                document.removeEventListener('pointercancel', up);
                onEnd(ev);
            }
            document.addEventListener('pointermove', move);
            document.addEventListener('pointerup', up);
            document.addEventListener('pointercancel', up);
        }

        grid.addEventListener('pointerdown', function (e) {
            if (e.button !== 0) return;

            // Resize by the bottom-right corner: width in grid columns (wide screens), height in 10 px steps.
            var handle = e.target.closest('.lw-resize');
            if (handle) {
                var it = handle.closest('.lw-item'), card = it.querySelector('.lw-card');
                var hasPlot = !!it.querySelector('.tg-plot');
                var gap = parseFloat(getComputedStyle(grid).columnGap) || 16;
                var colW = (grid.getBoundingClientRect().width + gap) / 12, left = it.getBoundingClientRect().left;
                var startY = e.clientY, startH = +it.getAttribute('data-h');
                var tip = document.createElement('span');
                tip.className = 'lw-size-tip';
                card.appendChild(tip);
                drag(e, function (ev) {
                    var w = window.innerWidth >= 1200 ? Math.max(2, Math.min(12, Math.round((ev.clientX - left + gap) / colW))) : +it.getAttribute('data-w');
                    var h = hasPlot ? Math.max(MIN, Math.min(MAX, Math.round((startH + ev.clientY - startY) / 10) * 10)) : startH;
                    setSize(it, w, h);
                    tip.textContent = w + '/12' + (hasPlot ? ' · ' + h + ' px' : '');
                }, function () { tip.remove(); save(); });
                return;
            }

            // Move by the title bar (not its buttons and links).
            var head = e.target.closest('.lw-head');
            if (!head || e.target.closest('a, button, input, select, form, .lw-tools')) return;
            var item = head.closest('.lw-item'), r = item.getBoundingClientRect();
            var dx = e.clientX - r.left, dy = e.clientY - r.top;
            var ph = document.createElement('div');
            ph.className = 'lw-item lw-placeholder';
            ph.style.cssText = item.style.cssText;
            ph.style.height = r.height + 'px';
            grid.insertBefore(ph, item);
            item.classList.add('is-dragging');
            item.style.width = r.width + 'px';
            item.style.left = r.left + 'px';
            item.style.top = r.top + 'px';
            drag(e, function (ev) {
                item.style.left = (ev.clientX - dx) + 'px';
                item.style.top = (ev.clientY - dy) + 'px';
                var under = document.elementFromPoint(ev.clientX, ev.clientY);
                var target = under && under.closest('.lw-item');
                if (target && target !== ph && target.parentNode === grid) {
                    var tr = target.getBoundingClientRect();
                    grid.insertBefore(ph, ev.clientX > tr.left + tr.width / 2 ? target.nextSibling : target);
                }
                if (ev.clientY < 70) window.scrollBy(0, -14);
                else if (ev.clientY > window.innerHeight - 70) window.scrollBy(0, 14);
            }, function () {
                item.classList.remove('is-dragging');
                item.style.width = item.style.left = item.style.top = '';
                grid.insertBefore(item, ph);
                ph.remove();
                save();
            });
        });
    })();

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
