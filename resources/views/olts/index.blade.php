@extends('adminlte::page')

@section('title', 'OLT Management')

@section('content_header')
<x-noc.header title="OLTs" icon="fas fa-network-wired" subtitle="All OLT devices, status, credentials and web access" />
@stop

@section('content')

@if(session('success'))

<div class="alert alert-success">
    {{ session('success') }}
</div>

@endif

<div class="card">

<div class="card-header">

    <div class="row">

        <div class="col-md-2">

            <a href="{{ route('olt.create', array_filter(['zone' => request('zone')])) }}"
               class="btn btn-primary btn-block">

                <i class="fas fa-plus"></i>
                {{ request('zone') ? 'Add OLTs here' : 'Add OLTs' }}

            </a>

        </div>

        <div class="col-md-10">

            <form method="GET"
                  action="{{ route('olt.index') }}">

                <div class="row">

                    <div class="col-md-3">

                        <select name="zone" class="form-control js-zone-select" data-placeholder="All zones ({{ $zoneStats->sum('count') }} OLTs)" onchange="this.form.submit()">
                            <option value=""></option>
                            @foreach ($zoneStats as $zoneName => $st)
                                <option value="{{ $zoneName }}" data-name="{{ $zoneName }}" data-count="{{ $st['count'] }}" data-down="{{ $st['down'] }}" data-pop="{{ $st['pop'] ? 1 : 0 }}" @selected(request('zone') === $zoneName)>{{ $zoneName }}</option>
                            @endforeach
                        </select>

                    </div>

                    <div class="col-md-2">

                        <input type="text"
                               name="search"
                               class="form-control"
                               placeholder="Name / IP Address"
                               value="{{ request('search') }}">

                    </div>

                    <div class="col-md-2">

                        <input type="text"
                               name="vlan"
                               class="form-control"
                               placeholder="VLAN"
                               value="{{ request('vlan') }}">

                    </div>

                    <div class="col-md-2">

                        <select name="status"
                                class="form-control">

                            <option value="">
                                All Status
                            </option>

                            <option value="1"
                                {{ request('status') == '1' ? 'selected' : '' }}>
                                Online
                            </option>

                            <option value="0"
                                {{ request('status') == '0' ? 'selected' : '' }}>
                                Offline
                            </option>

                        </select>

                    </div>

                    <div class="col-md-1">

                        <button type="submit"
                                class="btn btn-success btn-block">

                            <i class="fas fa-search"></i>

                        </button>

                    </div>

                    <div class="col-md-1">

                        <a href="{{ route('olt.index') }}"
                           class="btn btn-secondary btn-block">

                            <i class="fas fa-sync"></i>

                        </a>

                    </div>

                </div>

            </form>

        </div>

    </div>

</div>

<div class="card-body">

    <table class="table table-bordered table-striped data-table">

        <thead>

        <tr>

            <th class="text-center" style="width: 3rem" data-orderable="false" data-searchable="false">SL</th>
            <th>Zone</th>
            <th>Name</th>
            <th>Brand</th>
            <th>VLAN</th>
            <th>IP Address</th>
            <th>Status</th>
            <th width="420">Actions</th>

        </tr>

        </thead>

        <tbody>

        @forelse($olts as $olt)

            <tr>

                <td class="text-center text-muted">{{ $loop->iteration }}</td>

                <td>{{ $olt->zone }}</td>

                <td>{{ $olt->name }}</td>

                <td>{{ $olt->brand }}</td>

                <td class="text-center font-weight-bold">
                    {{ $olt->vlan }}
                </td>

                <td>{{ $olt->ip }}</td>

                <td>

                    @if($olt->status == 1)

                        <span class="badge badge-success">
                            ONLINE
                        </span>

                    @else

                        <span class="badge badge-danger">
                            OFFLINE
                        </span>

                    @endif

                </td>

                <td>

                    <a href="{{ route('olt.web',$olt->id) }}"
                       target="_blank"
                       class="btn btn-info btn-sm">

                        <i class="fas fa-globe"></i>
                        WEB

                    </a>

                    <a href="{{ route('olts.ping',$olt->id) }}"
                       target="_blank"
                       class="btn btn-success btn-sm">

                        <i class="fas fa-network-wired"></i>
                        PING

                    </a>

                    @if(strtolower(auth()->user()->role) == 'admin')

                    <a href="{{ route('olt.show',$olt->id) }}"
                     class="btn btn-warning btn-sm">
                     <i class="fas fa-key"></i>
                     CREDENTIALS
                    </a>

                    <a href="{{ route('olt.edit',$olt->id) }}"
                     class="btn btn-secondary btn-sm">
                     EDIT
                    </a>

                   <form action="{{ route('olt.destroy',$olt->id) }}"
                   method="POST"
                   style="display:inline;"
                   class="js-confirm-delete"
                   data-confirm-message="Delete OLT {{ $olt->name }}?">

                   @csrf
                   @method('DELETE')

                   <button type="submit"
                  class="btn btn-danger btn-sm">
                   DELETE
                     </button>

                  </form>

                @endif

                </td>

            </tr>

        @empty

            <tr>

                <td colspan="8"
                    class="text-center">

                    No OLT Found

                </td>

            </tr>

        @endforelse

        </tbody>

    </table>

</div>

</div>

<script>

// Refresh every 30s for fresh status — but not while someone is picking a
// zone or typing in a filter (it would close the dropdown / lose the text).
setInterval(function () {
    var busy = document.querySelector('.select2-container--open')
        || (document.activeElement && /^(INPUT|SELECT|TEXTAREA)$/.test(document.activeElement.tagName));
    if (!busy) location.reload();
}, 30000);

</script>

<style>

.table tbody td{
    font-weight:bold;
}

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
