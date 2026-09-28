@extends('adminlte::page')

@section('title', 'Edit OLT')

@section('content_header')
<x-noc.header title="Edit OLT" subtitle="{{ $olt->name }} · {{ $olt->ip }}" :back="route('olt.index')" />
@stop

@section('content')

<div class="card card-outline card-primary">

    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-network-wired mr-1"></i>
            {{ $olt->name }}
        </h3>
    </div>

    <form action="{{ route('olt.update',$olt->id) }}"
          method="POST">

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
                                    <option value="{{ $zone }}" {{ old('zone', $olt->zone) == $zone ? 'selected' : '' }}>
                                        {{ $zone }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>OLT Name</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-tag"></i></span>
                            </div>
                            <input type="text"
                                   name="name"
                                   value="{{ $olt->name }}"
                                   class="form-control">
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
                                   value="{{ $olt->brand }}"
                                   class="form-control">
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
                                   value="{{ $olt->ip }}"
                                   class="form-control">
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
                                    value="{{ $olt->vlan }}"
                                    class="form-control">
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
                                   value="{{ $olt->username }}"
                                   class="form-control">
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
                                   value="{{ $olt->password }}"
                                   class="form-control">
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
                                   value="{{ $olt->snmp }}"
                                   class="form-control">
                        </div>
                    </div>

                </div>

            </div>

        </div>

        <div class="card-footer">

            <button type="submit"
                    class="btn btn-primary">
                <i class="fas fa-save"></i>
                Update OLT
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
