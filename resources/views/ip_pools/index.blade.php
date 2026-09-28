@extends('adminlte::page')

@section('title', 'IP Management')

@section('content_header')
<h1>IP Management</h1>
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
        <h3 class="card-title">IP Subnets</h3>
        <a href="{{ route('ip-pools.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Subnet
        </a>
    </div>

    <div class="card-body p-0">

        <table class="table table-bordered table-striped data-table mb-0">

            <thead>
                <tr>
                    <th>Subnet</th>
                    <th>Gateway</th>
                    <th>Type</th>
                    <th>Zone</th>
                    <th>VLAN</th>
                    <th>Status</th>
                    <th width="150">Actions</th>
                </tr>
            </thead>

            <tbody>

                @forelse($ipPools as $ip)

                    <tr>
                        <td class="font-weight-bold">{{ $ip->subnet }}</td>
                        <td>{{ $ip->gateway ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $ip->type == 'public' ? 'badge-info' : 'badge-secondary' }}">
                                {{ strtoupper($ip->type) }}
                            </span>
                        </td>
                        <td>{{ $ip->zone ?? '—' }}</td>
                        <td>{{ $ip->vlan ?? '—' }}</td>
                        <td>
                            @if($ip->status == 'active')
                                <span class="badge badge-success">ACTIVE</span>
                            @else
                                <span class="badge badge-danger">INACTIVE</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('ip-pools.edit', $ip->id) }}" class="btn btn-warning btn-sm">
                                Edit
                            </a>

                            <form action="{{ route('ip-pools.destroy', $ip->id) }}"
                                  method="POST"
                                  class="d-inline js-confirm-delete"
                                  data-confirm-message="Delete subnet {{ $ip->subnet }}?">
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
                        <td colspan="7" class="text-center">No IP subnets recorded yet</td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>

@stop
