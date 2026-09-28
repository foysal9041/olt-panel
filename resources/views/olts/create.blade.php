@extends('adminlte::page')

@section('title', 'Add OLT')

@section('content_header')
<x-noc.header title="Add OLT" subtitle="Register a new OLT device" :back="route('olt.index')" />
@stop

@section('content')

<div class="card card-outline card-primary">

    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-network-wired mr-1"></i>
            OLT Information
        </h3>
    </div>

    <form method="POST" action="{{ route('olt.store') }}">

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

            <div class="row">

                <div class="col-md-6">

                    <h6 class="text-muted text-uppercase small font-weight-bold mb-3">Identification</h6>

                    <div class="form-group">
                        <label>Zone</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                            </div>
                            <select name="zone" class="form-control select2-zone" required>
                                <option value="">Select a zone</option>
                                @foreach($zones as $zone)
                                    <option value="{{ $zone }}" {{ old('zone') == $zone ? 'selected' : '' }}>
                                        {{ $zone }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @if($zones->isEmpty())
                            <small class="text-danger">
                                No zones yet — <a href="{{ route('zones.create') }}">add one first</a>.
                            </small>
                        @endif
                    </div>

                    <div class="form-group">
                        <label>OLT Name</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-tag"></i></span>
                            </div>
                            <input type="text"
                                   name="name"
                                   class="form-control"
                                   placeholder="Example: BDCOM"
                                   required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Brand</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-industry"></i></span>
                            </div>
                            <input type="text"
                                   name="brand"
                                   class="form-control"
                                   placeholder="BDCOM / VSOL / C-DATA / ZTE / HUAWEI"
                                   required>
                        </div>
                    </div>

                <div class="col-md-6">

                    <h6 class="text-muted text-uppercase small font-weight-bold mb-3">Connection &amp; Credentials</h6>

                    <div class="form-group">
                        <label>IP Address</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-globe"></i></span>
                            </div>
                            <input type="text"
                                   name="ip"
                                   class="form-control"
                                   placeholder="Example: 0.0.0.0"
                                   required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>VLAN</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-sitemap"></i></span>
                            </div>
                            <input type="text"
                                    name="vlan"
                                    class="form-control"
                                    placeholder="10-15">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Username</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                            </div>
                            <input type="text"
                                   name="username"
                                   class="form-control"
                                   placeholder="Example: Hasan_ali"
                                   required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            </div>
                            <input type="password"
                                   name="password"
                                   class="form-control"
                                   placeholder="Password"
                                   required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>SNMP Community</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                            </div>
                            <input type="text"
                                   name="snmp"
                                   class="form-control"
                                   value="public"
                                   required>
                        </div>
                    </div>

                </div>

            </div>

        </div>

        <div class="card-footer">

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i>
                Save OLT
            </button>

            <a href="{{ route('olt.index') }}"
               class="btn btn-secondary">
                Cancel
            </a>

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
