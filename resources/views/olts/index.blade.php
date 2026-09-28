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

            <a href="{{ route('olt.create') }}"
               class="btn btn-primary btn-block">

                <i class="fas fa-plus"></i>
                Add OLT

            </a>

        </div>

        <div class="col-md-10">

            <form method="GET"
                  action="{{ route('olt.index') }}">

                <div class="row">

                    <div class="col-md-2">

                        <input type="text"
                               name="zone"
                               class="form-control"
                               placeholder="Zone"
                               value="{{ request('zone') }}">

                    </div>

                    <div class="col-md-3">

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

            <th>ID</th>
            <th>Zone</th>
            <th>Name</th>
            <th>Brand</th>
            <th>Model</th>
            <th>VLAN</th>
            <th>IP Address</th>
            <th>Status</th>
            <th width="420">Actions</th>

        </tr>

        </thead>

        <tbody>

        @forelse($olts as $olt)

            <tr>

                <td>{{ $olt->id }}</td>

                <td>{{ $olt->zone }}</td>

                <td>{{ $olt->name }}</td>

                <td>{{ $olt->brand }}</td>

                <td>{{ $olt->model }}</td>

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

                <td colspan="9"
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

setInterval(function () {
    location.reload();
}, 30000);

</script>

<style>

.table tbody td{
    font-weight:bold;
}

</style>

@stop

