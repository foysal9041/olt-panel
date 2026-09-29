@extends('adminlte::page')

@section('title', 'Zones')

@section('content_header')
<x-noc.header title="Zones" icon="fas fa-map-marked-alt" subtitle="Service areas used across OLTs, staff and customers" />
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card card-outline card-primary">

    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Zones <span class="badge badge-secondary ml-1">{{ $zones->count() }}</span></h3>
        <a href="{{ route('zones.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Zone
        </a>
    </div>

    <div class="card-body p-0">

        <table class="table table-hover data-table mb-0 zone-table">

            <thead>
                <tr>
                    <th class="text-center" style="width: 3rem" data-orderable="false" data-searchable="false">SL</th>
                    <th>Zone / POP</th>
                    <th>Code</th>
                    <th>Username</th>
                    <th>Contact</th>
                    <th class="text-center">OLTs</th>
                    <th class="text-center">Switches</th>
                    <th width="120" data-orderable="false">Actions</th>
                </tr>
            </thead>

            <tbody>

                @foreach($zones as $zone)

                    <tr>
                        <td class="text-center text-muted">{{ $loop->iteration }}</td>
                        <td>
                            <strong style="color:#0f172a">{{ $zone->name }}</strong>
                            @if ($zone->notes)
                                <i class="fas fa-sticky-note text-warning ml-1" title="{{ $zone->notes }}"></i>
                            @endif
                        </td>
                        <td class="mono">{{ $zone->code ?: '—' }}</td>
                        <td class="mono small">{{ $zone->username ?: '—' }}</td>
                        <td class="small">
                            @if ($zone->contact_name) <div class="font-weight-bold">{{ $zone->contact_name }}</div> @endif
                            @foreach ($zone->phones() as $ph)
                                <div><i class="fas fa-phone-alt text-muted mr-1"></i><a href="tel:{{ $ph }}">{{ $ph }}</a></div>
                            @endforeach
                            @if ($zone->email)
                                <div class="text-muted"><i class="fas fa-envelope mr-1"></i>{{ $zone->email }}</div>
                            @endif
                            @if (! $zone->contact_name && ! $zone->phone && ! $zone->email) <span class="text-muted">—</span> @endif
                        </td>
                        <td class="text-center">{{ $zone->olts_count ?: '—' }}</td>
                        <td class="text-center">{{ $switchCounts[$zone->name] ?? '—' }}</td>
                        <td class="text-nowrap">
                            <a href="{{ route('zones.edit', $zone->id) }}" class="btn btn-light btn-sm" title="Edit"><i class="fas fa-edit"></i></a>

                            <form action="{{ route('zones.destroy', $zone->id) }}"
                                  method="POST"
                                  class="d-inline js-confirm-delete"
                                  data-confirm-message="Delete zone {{ $zone->name }}?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-light btn-sm text-danger" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>

                @endforeach

            </tbody>

        </table>

        @if ($zones->isEmpty())
            <div class="text-center text-muted py-4">No zones yet</div>
        @endif

    </div>

</div>

@stop

@section('css')
<style>
    .zone-table td { vertical-align: middle; }
    .zone-table .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
</style>
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
</script>
@stop
