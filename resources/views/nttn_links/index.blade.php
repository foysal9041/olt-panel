@extends('adminlte::page')

@section('title', 'NTTN Link Management')

@section('content_header')
<x-noc.header title="NTTN Links" icon="fas fa-project-diagram" subtitle="Transmission links, bandwidth, peering and ASN" />
@stop

@section('css')
<style>
    /* Long POP addresses: keep the column narrow, two lines max, full text on hover */
    .nttn-address { width: 190px; max-width: 190px; }
    td.nttn-address { font-size: .82rem; color: #475569; line-height: 1.35; }
    td.nttn-address span {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        word-break: break-word;
    }
</style>
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
        <h3 class="card-title">NTTN Links</h3>
        <a href="{{ route('nttn-links.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add NTTN Link
        </a>
    </div>

    <div class="card-body p-0">

        <table class="table table-bordered table-striped data-table mb-0">

            <thead>
                <tr>
                    <th class="text-center" style="width: 3rem" data-orderable="false" data-searchable="false">SL</th>
                    <th>Link ID</th>
                    <th>Provider</th>
                    <th class="nttn-address">Address</th>
                    <th>Bandwidth</th>
                    <th>Location</th>
                    <th>Zone</th>
                    <th>Status</th>
                    <th>Link Status</th>
                    <th width="200">Actions</th>
                </tr>
            </thead>

            <tbody>

                @forelse($nttnLinks as $link)

                    <tr>
                        <td class="text-center text-muted js-sl">{{ $loop->iteration }}</td>
                        <td class="font-weight-bold">{{ $link->link_id }}</td>
                        <td>{{ $link->provider ?? '—' }}</td>
                        <td class="nttn-address" title="{{ $link->address }}"><span>{{ $link->address }}</span></td>
                        <td>{{ $link->bandwidth }}</td>
                        <td>{{ $link->location }}</td>
                        <td>{{ $link->zone ?? '—' }}</td>
                        <td>
                            @if($link->status == 'active')
                                <span class="badge badge-success">ACTIVE</span>
                            @else
                                <span class="badge badge-danger">INACTIVE</span>
                            @endif
                        </td>
                        <td>@include('nttn_links._ping', ['link' => $link])</td>
                        <td class="text-nowrap">

                            <a href="{{ route('nttn-links.show', $link->id) }}" class="btn btn-info btn-sm">
                                View
                            </a>

                            <a href="{{ route('nttn-links.edit', $link->id) }}" class="btn btn-warning btn-sm">
                                Edit
                            </a>

                            <form action="{{ route('nttn-links.destroy', $link->id) }}"
                                  method="POST"
                                  class="d-inline js-confirm-delete"
                                  data-confirm-message="Delete NTTN link {{ $link->link_id }}?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>

                @empty

                    <tr>
                        <td colspan="10" class="text-center">No NTTN links recorded yet</td>
                    </tr>

                @endforelse

            </tbody>

        </table>

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

// Link status comes from the every-minute monitor; keep it current.
setTimeout(function () { location.reload(); }, 60000);
</script>
@stop
