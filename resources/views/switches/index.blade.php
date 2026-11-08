@extends('adminlte::page')

@section('title', $type === 'router' ? 'Routers' : ($type === 'switch' ? 'Switches' : 'Switches & Routers'))

@section('content_header')
<x-noc.header :title="$type === 'router' ? 'Routers' : ($type === 'switch' ? 'Switches' : 'Switches & Routers')" :icon="$type === 'router' ? 'fas fa-route' : 'fas fa-server'"
    :subtitle="$type === 'router' ? 'SNMP-monitored routers: interfaces, VLANs, traffic, CPU and memory' : ($type === 'switch' ? 'SNMP-monitored switches: ports, traffic and SFP transceivers' : 'Every SNMP-monitored switch and router')" />
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if ($switches->contains(fn ($s) => $s->notify))
    @include('partials.telegram-off-banner')
@endif

<div class="btn-group btn-group-sm mb-3" role="group" aria-label="Device type">
    <a href="{{ route('switches.index') }}" class="btn {{ $type === 'switch' ? 'btn-primary' : 'btn-outline-secondary' }}"><i class="fas fa-server"></i> Switches ({{ $counts['switch'] ?? 0 }})</a>
    <a href="{{ route('routers.index') }}" class="btn {{ $type === 'router' ? 'btn-primary' : 'btn-outline-secondary' }}"><i class="fas fa-route"></i> Routers ({{ $counts['router'] ?? 0 }})</a>
    <a href="{{ route('switches.index', ['type' => 'all']) }}" class="btn {{ $type === null ? 'btn-primary' : 'btn-outline-secondary' }}"><i class="fas fa-list"></i> All devices ({{ $counts->sum() }})</a>
</div>

<div class="acct-stats is-compact" data-live="sw-stats">
    @foreach ([
        [$type === 'router' ? 'Routers' : ($type === 'switch' ? 'Switches' : 'Devices'), $summary['total'], '#0284c7', $type === 'router' ? 'fas fa-route' : 'fas fa-server'],
        ['Up', $summary['up'], '#16a34a', 'fas fa-check'],
        ['Down', $summary['down'], '#e11d48', 'fas fa-times'],
        ['Ports Down (enabled)', $summary['ports_down'], '#d97706', 'fas fa-plug'],
    ] as [$label, $value, $accent, $icon])
        <div class="acct-stat" style="--accent: {{ $accent }}">
            <div class="acct-stat-label">{{ \App\Support\Ui::t($label) }} <i class="{{ $icon }}"></i></div>
            <div class="acct-stat-value {{ $label === 'Down' && $value ? 'text-danger' : '' }}">{{ $value }}</div>
        </div>
    @endforeach
</div>

{{-- Moved beside the table's search box by the script below. --}}
@unless (auth()->user()->isViewer())
    <div id="table-actions" class="d-flex justify-content-end mb-2" style="gap:.4rem">
        @if ($type !== 'router')
            <a href="{{ route('switches.create') }}" class="btn btn-primary btn-sm text-nowrap"><i class="fas fa-plus"></i> Add Switch</a>
        @endif
        @if ($type !== 'switch')
            <a href="{{ route('routers.create') }}" class="btn {{ $type === 'router' ? 'btn-primary' : 'btn-outline-primary' }} btn-sm text-nowrap"><i class="fas fa-plus"></i> Add Router</a>
        @endif
    </div>
@endunless

<div class="card card-outline card-primary">

    <div class="card-header">
        <h3 class="card-title">{{ \App\Support\Ui::t($type === 'router' ? 'All Routers' : ($type === 'switch' ? 'All Switches' : 'All Switches & Routers')) }}</h3>
    </div>

    <div class="card-body p-0 table-responsive">

        <table class="table table-bordered table-striped table-hover data-table mb-0" data-live-table="switches">

            <thead>
                <tr>
                    <th class="text-center" style="width: 3rem" data-orderable="false" data-searchable="false">SL</th>
                    <th>Status</th>
                    <th>Name</th>
                    <th>IP</th>
                    <th>Vendor</th>
                    <th>Zone</th>
                    <th>Ports Up</th>
                    <th>SFP</th>
                    <th>CPU</th>
                    <th>Uptime</th>
                    <th>Last Poll</th>
                    <th width="170">Actions</th>
                </tr>
            </thead>

            <tbody>

                @foreach($switches as $switch)

                    <tr data-key="{{ $switch->id }}">
                        <td class="text-center text-muted" data-live-ignore>{{ $loop->iteration }}</td>
                        <td data-order="{{ $switch->status ?? -1 }}">
                            @include('switches._status', ['status' => $switch->status])
                            @unless ($switch->is_active)
                                <span class="badge badge-light">Paused</span>
                            @endunless
                        </td>
                        <td>
                            <a href="{{ route('switches.show', $switch) }}"><strong>{{ $switch->name }}</strong></a>
                            @if ($switch->isRouter())<span class="badge badge-light border ml-1"><i class="fas fa-route"></i> Router</span>@endif
                            @if ($switch->sys_name)
                                <div class="small text-muted">{{ $switch->sys_name }}</div>
                            @endif
                        </td>
                        <td><code>{{ $switch->ip }}</code></td>
                        <td>{{ $switch->vendor_label }}</td>
                        <td>{{ $switch->zone ?: '—' }}</td>
                        <td data-order="{{ $switch->ports_up_count }}">
                            {{ $switch->ports_up_count }} / {{ $switch->ports_count }}
                        </td>
                        <td data-order="{{ $switch->ports_sfp_count }}">
                            {{ $switch->ports_sfp_count }}
                            @if ($switch->ports_rx_alarm_count)
                                <span class="badge badge-warning" title="Ports with low Rx power">
                                    <i class="fas fa-exclamation-triangle"></i> {{ $switch->ports_rx_alarm_count }}
                                </span>
                            @endif
                        </td>
                        <td data-order="{{ $switch->hasCurrentCpu() ? $switch->cpu_percent : -1 }}">
                            @if ($switch->hasCurrentCpu())
                                <span class="{{ $switch->cpu_percent >= 85 ? 'text-danger font-weight-bold' : ($switch->cpu_percent >= 60 ? 'text-warning font-weight-bold' : '') }}">{{ $switch->cpu_percent }}%</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td data-order="{{ $switch->uptime_seconds ?? -1 }}">{{ $switch->uptime_human ?? '—' }}</td>
                        <td data-order="{{ $switch->last_polled_at?->timestamp ?? 0 }}">
                            {{ $switch->last_polled_at?->diffForHumans() ?? 'never' }}
                            @if ($switch->last_error)
                                <i class="fas fa-exclamation-circle text-danger" title="{{ $switch->last_error }}"></i>
                            @endif
                        </td>
                        <td class="text-nowrap">
                            <a href="{{ route('switches.show', $switch) }}" class="btn btn-info btn-sm" title="Ports">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="{{ route('switches.edit', $switch) }}" class="btn btn-warning btn-sm" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="{{ route('switches.destroy', $switch) }}"
                                  method="POST"
                                  class="d-inline js-confirm-delete"
                                  data-confirm-message="Delete switch {{ $switch->name }} and its port history?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>

                @endforeach

            </tbody>

        </table>

        @if ($switches->isEmpty())
            <div class="text-center text-muted py-4">
                No switches yet. <a href="{{ route('switches.create') }}">Add your first switch</a>.
            </div>
        @endif

    </div>

</div>

@stop

@section('js')
<script>
// SL numbers follow the table's current order/search/page, 1..n.
$(function () {
    setTimeout(function () {
        var $table = $('table.data-table');
        if (!$.fn.dataTable || !$.fn.dataTable.isDataTable($table)) return;

        var dt = $table.DataTable();

        // "Add Router / Switch" beside the search box.
        var $filter = $(dt.table().container()).find('.dataTables_filter');
        var $actions = $('#table-actions');
        if ($filter.length && $actions.length) {
            $filter.addClass('has-actions').append($actions.children());
            $actions.remove();
        }

        dt.on('draw.dt', function () {
            var start = dt.page.info().start;
            dt.column(0, { search: 'applied', order: 'applied', page: 'current' }).nodes().each(function (cell, i) {
                cell.textContent = start + i + 1;
            });
        }).draw(false);
    }, 0);
});

// Status, ports and Rx update in place every 30s.
LiveRefresh.start(30000);
</script>
@stop
