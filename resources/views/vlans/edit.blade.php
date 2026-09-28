@extends('adminlte::page')

@section('title', 'Edit VLAN')

@section('content_header')
<h1>Edit VLAN</h1>
@stop

@section('content')

<div class="card card-outline card-primary col-md-6">

    <div class="card-header">
        <h3 class="card-title">{{ $vlan->vlan }} — {{ $vlan->name }}</h3>
    </div>

    <form method="POST" action="{{ route('vlans.update', $vlan->id) }}">
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
                <label>VLAN</label>
                <input type="text" name="vlan" class="form-control" value="{{ old('vlan', $vlan->vlan) }}" required autofocus>
            </div>

            <div class="form-group">
                <label>Name / Purpose</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $vlan->name) }}" required>
            </div>

            <div class="form-group">
                <label>Zone</label>
                <select name="zone" class="form-control select2-zone">
                    <option value="">— None —</option>
                    @foreach($zones as $zone)
                        <option value="{{ $zone }}" {{ old('zone', $vlan->zone) == $zone ? 'selected' : '' }}>
                            {{ $zone }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="active" {{ old('status', $vlan->status) == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="reserved" {{ old('status', $vlan->status) == 'reserved' ? 'selected' : '' }}>Reserved</option>
                </select>
            </div>

            <div class="form-group">
                <label>Remarks</label>
                <textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $vlan->remarks) }}</textarea>
            </div>

        </div>

        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('vlans.index') }}" class="btn btn-secondary">Cancel</a>
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
