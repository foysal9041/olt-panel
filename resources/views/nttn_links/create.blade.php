@extends('adminlte::page')

@section('title', 'Add NTTN Link')

@section('content_header')
<x-noc.header title="Add NTTN Link" :back="route('nttn-links.index')" />
@stop

@section('content')

<div class="card card-outline card-primary col-md-6">

    <div class="card-header">
        <h3 class="card-title">NTTN Link Details</h3>
    </div>

    <form method="POST" action="{{ route('nttn-links.store') }}">
        @csrf

        <div class="card-body">

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="form-group">
                <label>NTTN Link ID</label>
                <input type="text" name="link_id" class="form-control" value="{{ old('link_id') }}"
                       placeholder="e.g. NTTN-FH-00123" required autofocus>
            </div>

            <div class="form-group">
                <label>Provider</label>
                <input type="text" name="provider" class="form-control" value="{{ old('provider') }}"
                       placeholder="e.g. Fiber@Home, Summit, BSCCL">
            </div>

            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" class="form-control" value="{{ old('address') }}"
                       placeholder="POP / entry point address" required>
            </div>

            <div class="form-group">
                <label>Current Bandwidth</label>
                <input type="text" name="bandwidth" class="form-control" value="{{ old('bandwidth') }}"
                       placeholder="e.g. 1 Gbps" required>
            </div>

            <h6 class="text-muted text-uppercase small font-weight-bold mb-3 mt-4">Networking</h6>

            <div class="form-group">
                <label>Public IP Subnet</label>
                <input type="text" name="public_ip_subnet" class="form-control" value="{{ old('public_ip_subnet') }}"
                       placeholder="e.g. 103.150.10.0/29">
            </div>

            <div class="form-group">
                <label>Private IP Subnet</label>
                <input type="text" name="private_ip_subnet" class="form-control" value="{{ old('private_ip_subnet') }}"
                       placeholder="e.g. 10.10.10.0/30">
            </div>

            <div class="form-group">
                <label>Peering IP</label>
                <input type="text" name="peering_ip" class="form-control" value="{{ old('peering_ip') }}"
                       placeholder="e.g. 10.10.10.1">
            </div>

            <div class="form-group">
                <label>Peering VLAN</label>
                <input type="text" name="peering_vlan" class="form-control" value="{{ old('peering_vlan') }}"
                       placeholder="e.g. 300">
            </div>

            <div class="form-group">
                <label>ASN</label>
                <input type="text" name="asn" class="form-control" value="{{ old('asn') }}"
                       placeholder="e.g. AS137074">
            </div>

            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location" class="form-control" value="{{ old('location') }}"
                       placeholder="e.g. Jashore POP" required>
            </div>

            <div class="form-group">
                <label>Zone</label>
                <select name="zone" class="form-control select2-zone">
                    <option value="">— None —</option>
                    @foreach($zones as $zone)
                        <option value="{{ $zone }}" {{ old('zone') == $zone ? 'selected' : '' }}>
                            {{ $zone }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>

            <div class="form-group">
                <label>Remarks</label>
                <textarea name="remarks" class="form-control" rows="2">{{ old('remarks') }}</textarea>
            </div>

        </div>

        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('nttn-links.index') }}" class="btn btn-secondary">Cancel</a>
        </div>

    </form>

</div>

<script>
$(function () {
    $('.select2-zone').select2({
        theme: 'bootstrap4',
        width: '100%',
    });
});
</script>

@stop
