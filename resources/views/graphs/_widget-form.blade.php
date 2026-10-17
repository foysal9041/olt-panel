{{--
    Add / edit one graph of a dashboard. $widget is null when adding.
    Needs $dashboard, $switches (each with ->ports that have counters) and
    $otherPorts (on the dashboard already, from switches the editor can't see).
--}}
@php
    $w = $widget;
    $kind = $w->kind ?? old('kind', 'traffic');
    $selected = $w ? $w->portIds() : array_map('intval', (array) old('ports', []));
    $selSwitches = $w ? $w->switchIds() : array_map('intval', (array) old('switches', []));
    $graphTitles = collect(\App\Models\SwitchPortTraffic::GRAPHS)->map(fn ($g) => \App\Support\Ui::t($g[0]));
    $uid = 'wf' . ($w->id ?? 'new');
@endphp
<form method="POST" action="{{ $w ? route('graphs.widgets.update', [$dashboard, $w]) : route('graphs.widgets.store', $dashboard) }}" class="js-widget-form" id="{{ $uid }}">
    @csrf
    @if ($w) @method('PUT') @endif

    <div class="form-row">
        <div class="col-md-4 form-group">
            <label class="small font-weight-bold mb-1">Type</label>
            <select name="kind" class="form-control form-control-sm js-kind">
                @foreach (\App\Models\GraphWidget::KINDS as $key => $label)
                    <option value="{{ $key }}" @selected($kind === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-8 form-group">
            <label class="small font-weight-bold mb-1">Title <small class="text-muted">(optional)</small></label>
            <input type="text" name="title" maxlength="150" class="form-control form-control-sm" value="{{ $w->title ?? old('title') }}" placeholder="e.g. Total upstream — blank shows the interface name">
        </div>
    </div>

    <div class="form-group js-for-traffic">
        <label class="small font-weight-bold mb-1">Interfaces</label>
        <select name="ports[]" multiple class="form-control js-select2" data-placeholder="Search a switch, interface or description…">
            @foreach ($switches as $sw)
                @if ($sw->ports->isNotEmpty())
                    <optgroup label="{{ $sw->isRouter() ? 'Router · ' : '' }}{{ $sw->name }} ({{ $sw->ip }})">
                        @foreach ($sw->ports as $p)
                            <option value="{{ $p->id }}" @selected(in_array($p->id, $selected, true))>{{ $sw->name }} · {{ $p->label }}{{ $p->alias ? ' — ' . $p->alias : '' }}{{ $p->speed_label ? ' (' . $p->speed_label . ')' : '' }}</option>
                        @endforeach
                    </optgroup>
                @endif
            @endforeach
            @if ($otherPorts->isNotEmpty())
                <optgroup label="Already on this dashboard (switch outside your access)">
                    @foreach ($otherPorts as $p)
                        <option value="{{ $p->id }}" @selected(in_array($p->id, $selected, true))>{{ $p->networkSwitch?->name }} · {{ $p->label }}{{ $p->alias ? ' — ' . $p->alias : '' }}</option>
                    @endforeach
                </optgroup>
            @endif
        </select>
        <small class="text-muted">One interface gets its own graph. Several are <strong>added up</strong> into one graph — e.g. all IIG uplinks = total upstream.</small>
        <div class="custom-control custom-checkbox mt-1">
            <input type="checkbox" class="custom-control-input" id="{{ $uid }}-mirror" name="mirror" value="1" @checked($w ? $w->mirror() : old('mirror'))>
            <label class="custom-control-label small" for="{{ $uid }}-mirror">Draw Out traffic below the axis (mirrored)</label>
        </div>
    </div>

    <div class="form-row js-for-cpu">
      <div class="col-md-8 form-group">
        <label class="small font-weight-bold mb-1">Switch / router</label>
        <select name="device" class="form-control js-select2" data-placeholder="Pick a device">
            <option value=""></option>
            @foreach ($switches as $sw)
                <option value="{{ $sw->id }}" @selected(($w ? $w->deviceId() : (int) old('device')) === $sw->id)>{{ $sw->isRouter() ? 'Router · ' : '' }}{{ $sw->name }} ({{ $sw->ip }})</option>
            @endforeach
        </select>
      </div>
      <div class="col-md-4 form-group">
        <label class="small font-weight-bold mb-1">Graph</label>
        <select name="metric" class="form-control form-control-sm">
            @foreach (\App\Models\GraphWidget::DEVICE_METRICS as $m)
                <option value="{{ $m }}" @selected(($w ? $w->metric() : old('metric', 'cpu')) === $m)>{{ \App\Support\Ui::t(\App\Services\GraphEngine::TYPES['device_' . $m][0]) }}</option>
            @endforeach
        </select>
      </div>
    </div>

    <div class="form-row js-for-top">
        <div class="col-md-9 form-group">
            <label class="small font-weight-bold mb-1">Switches <small class="text-muted">(blank = all you can see)</small></label>
            <select name="switches[]" multiple class="form-control js-select2" data-placeholder="All switches">
                @foreach ($switches as $sw)
                    <option value="{{ $sw->id }}" @selected(in_array($sw->id, $selSwitches, true))>{{ $sw->name }} ({{ $sw->ip }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 form-group">
            <label class="small font-weight-bold mb-1">How many</label>
            <input type="number" name="limit" min="3" max="25" class="form-control form-control-sm" value="{{ $w ? $w->limit() : old('limit', 10) }}">
        </div>
    </div>

    <div class="form-row align-items-end">
        <div class="col-6 col-md-4 form-group">
            <label class="small font-weight-bold mb-1">Size</label>
            <select name="width" class="form-control form-control-sm">
                @foreach (\App\Models\GraphWidget::WIDTHS as $cols => $label)
                    <option value="{{ $cols }}" @selected((int) ($w->width ?? old('width', 4)) === $cols)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-4 form-group js-for-graph">
            <label class="small font-weight-bold mb-1">Period</label>
            <select name="graph" class="form-control form-control-sm">
                <option value="">Dashboard default ({{ $graphTitles[$dashboard->default_graph] ?? 'Daily' }})</option>
                @foreach ($graphTitles as $key => $title)
                    <option value="{{ $key }}" @selected(($w->graph ?? old('graph')) === $key)>{{ $title }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 form-group">
            <button class="btn btn-success btn-sm btn-block"><i class="fas fa-check"></i> {{ $w ? 'Save graph' : 'Add to dashboard' }}</button>
        </div>
    </div>
</form>
