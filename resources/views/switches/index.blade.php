@extends('adminlte::page')

@section('title', 'Switches')

@section('content_header')
<x-noc.header title="Switches" icon="fas fa-server" subtitle="SNMP-monitored switches, ports and SFP transceivers">
    <a href="{{ route('switches.create') }}" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> Add Switch
    </a>
</x-noc.header>
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

<div class="row">
    @foreach ([
        ['Total Switches', $summary['total'], 'bg-info', 'fas fa-server'],
        ['Switches Up', $summary['up'], 'bg-success', 'fas fa-check'],
        ['Switches Down', $summary['down'], 'bg-danger', 'fas fa-times'],
        ['Ports Down (enabled)', $summary['ports_down'], 'bg-warning', 'fas fa-plug'],
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

<div class="card card-outline card-primary">

    <div class="card-header">
        <h3 class="card-title">All Switches</h3>
    </div>

    <div class="card-body p-0 table-responsive">

        <table class="table table-bordered table-striped table-hover data-table mb-0">

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
                    <th>Uptime</th>
                    <th>Last Poll</th>
                    <th width="170">Actions</th>
                </tr>
            </thead>

            <tbody>

                @foreach($switches as $switch)

                    <tr>
                        <td class="text-center text-muted">{{ $loop->iteration }}</td>
                        <td data-order="{{ $switch->status ?? -1 }}">
                            @include('switches._status', ['status' => $switch->status])
                            @unless ($switch->is_active)
                                <span class="badge badge-light">Paused</span>
                            @endunless
                        </td>
                        <td>
                            <a href="{{ route('switches.show', $switch) }}"><strong>{{ $switch->name }}</strong></a>
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
        dt.on('draw.dt', function () {
            var start = dt.page.info().start;
            dt.column(0, { search: 'applied', order: 'applied', page: 'current' }).nodes().each(function (cell, i) {
                cell.textContent = start + i + 1;
            });
        }).draw(false);
    }, 0);
});

setTimeout(function () { location.reload(); }, 60000);
</script>
@stop
