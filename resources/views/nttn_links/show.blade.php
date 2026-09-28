@extends('adminlte::page')

@section('title', 'NTTN Link Details')

@section('content_header')
<x-noc.header title="{{ $nttnLink->link_id }}" subtitle="NTTN link details · {{ $nttnLink->provider }}" :back="route('nttn-links.index')" />
@stop

@section('content')

<div class="card card-outline card-info">

    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-project-diagram mr-1"></i>
            {{ $nttnLink->link_id }}
        </h3>
    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-6">

                <h6 class="text-muted text-uppercase small font-weight-bold mb-3">Link Details</h6>

                <div class="form-group">
                    <label>NTTN Link ID</label>
                    <input type="text" class="form-control" value="{{ $nttnLink->link_id }}" readonly>
                </div>

                <div class="form-group">
                    <label>Provider</label>
                    <input type="text" class="form-control" value="{{ $nttnLink->provider ?? '—' }}" readonly>
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <input type="text" class="form-control" value="{{ $nttnLink->address }}" readonly>
                </div>

                <div class="form-group">
                    <label>Current Bandwidth</label>
                    <input type="text" class="form-control" value="{{ $nttnLink->bandwidth }}" readonly>
                </div>

                <div class="form-group">
                    <label>Location</label>
                    <input type="text" class="form-control" value="{{ $nttnLink->location }}" readonly>
                </div>

                <div class="form-group">
                    <label>Zone</label>
                    <input type="text" class="form-control" value="{{ $nttnLink->zone ?? '—' }}" readonly>
                </div>

                <div class="form-group">
                    <label>Status</label><br>
                    @if($nttnLink->status == 'active')
                        <span class="badge badge-success">ACTIVE</span>
                    @else
                        <span class="badge badge-danger">INACTIVE</span>
                    @endif
                </div>

            </div>

            <div class="col-md-6">

                <h6 class="text-muted text-uppercase small font-weight-bold mb-3">Networking</h6>

                <div class="form-group">
                    <label>Public IP Subnet</label>
                    <input type="text" class="form-control" value="{{ $nttnLink->public_ip_subnet ?? '—' }}" readonly>
                </div>

                <div class="form-group">
                    <label>Private IP Subnet</label>
                    <input type="text" class="form-control" value="{{ $nttnLink->private_ip_subnet ?? '—' }}" readonly>
                </div>

                <div class="form-group">
                    <label>Peering IP</label>
                    <input type="text" class="form-control" value="{{ $nttnLink->peering_ip ?? '—' }}" readonly>
                </div>

                <div class="form-group">
                    <label>Peering VLAN</label>
                    <input type="text" class="form-control" value="{{ $nttnLink->peering_vlan ?? '—' }}" readonly>
                </div>

                <div class="form-group">
                    <label>ASN</label>
                    <input type="text" class="form-control" value="{{ $nttnLink->asn ?? '—' }}" readonly>
                </div>

                <div class="form-group">
                    <label>Remarks</label>
                    <textarea class="form-control" rows="3" readonly>{{ $nttnLink->remarks ?? '—' }}</textarea>
                </div>

            </div>

        </div>

    </div>

    <div class="card-footer">

        <a href="{{ route('nttn-links.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Back to List
        </a>

        <a href="{{ route('nttn-links.edit', $nttnLink->id) }}" class="btn btn-primary">
            <i class="fas fa-edit"></i>
            Edit NTTN Link
        </a>

    </div>

</div>

@stop
