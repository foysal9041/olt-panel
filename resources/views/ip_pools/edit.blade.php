@extends('adminlte::page')

@section('title', 'Edit IP Subnet')

@section('content_header')
<h1>Edit IP Subnet</h1>
@stop

@section('content')

<div class="card card-outline card-primary col-md-6">

    <div class="card-header">
        <h3 class="card-title">{{ $ipPool->subnet }}</h3>
    </div>

    <form method="POST" action="{{ route('ip-pools.update', $ipPool->id) }}">
        @csrf
        @method('PUT')

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
                <label>Subnet (CIDR)</label>
                <input type="text" name="subnet" class="form-control" value="{{ old('subnet', $ipPool->subnet) }}" required autofocus>
            </div>

            <div class="form-group">
                <label>Gateway</label>
                <input type="text" name="gateway" class="form-control" value="{{ old('gateway', $ipPool->gateway) }}">
            </div>

            <div class="form-group">
                <label>Type</label>
                <select name="type" class="form-control">
                    <option value="public" {{ old('type', $ipPool->type) == 'public' ? 'selected' : '' }}>Public</option>
                    <option value="private" {{ old('type', $ipPool->type) == 'private' ? 'selected' : '' }}>Private</option>
                </select>
            </div>

            <div class="form-group">
                <label>Zone</label>
                <select name="zone" class="form-control select2-zone">
                    <option value="">— None —</option>
                    @foreach($zones as $zone)
                        <option value="{{ $zone }}" {{ old('zone', $ipPool->zone) == $zone ? 'selected' : '' }}>
                            {{ $zone }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>VLAN</label>
                <input type="text" name="vlan" class="form-control" value="{{ old('vlan', $ipPool->vlan) }}">
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="active" {{ old('status', $ipPool->status) == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ old('status', $ipPool->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="2">{{ old('description', $ipPool->description) }}</textarea>
            </div>

        </div>

        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('ip-pools.index') }}" class="btn btn-secondary">Cancel</a>
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
