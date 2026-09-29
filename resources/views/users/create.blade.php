@extends('adminlte::page')

@section('title','Add User')

@section('content')

<div class="card">

<div class="card-header">
    <h3 class="card-title">Add User</h3>
</div>

<div class="card-body">

    <form method="POST" action="{{ route('users.store') }}">

        @csrf

        <div class="form-group">
            <label>Name</label>

            <input type="text"
                   name="name"
                   class="form-control"
                   required>
        </div>

        <div class="form-group">
            <label>Email</label>

            <input type="email"
                   name="email"
                   class="form-control"
                   required>
        </div>

        <div class="form-group">
            <label>Username</label>

            <input type="text"
                   name="username"
                   class="form-control"
                   required>
        </div>

        <div class="form-group">
            <label>Password</label>

            <input type="password"
                   name="password"
                   class="form-control"
                   required>
        </div>

        <div class="form-group">
            <label>Role</label>

            <select name="role" class="form-control">

                <option value="admin">
                    Admin
                </option>

                <option value="noc">
                    NOC
                </option>

                <option value="operator">
                    Operator
                </option>

                <option value="viewer">
                    Viewer
                </option>

            </select>

        </div>

        <div class="form-group">
            <label>Zone</label>

            <select name="zone" class="form-control select2-zone">

                <option value="all">
                    All Zones
                </option>

                @foreach($zones as $zone)

                    <option value="{{ $zone }}">
                        {{ $zone }}
                    </option>

                @endforeach

            </select>

        </div>

        <div class="form-group">
            <label>Status</label>

            <select name="status" class="form-control">

                <option value="1">
                    Active
                </option>

                <option value="0">
                    Disabled
                </option>

            </select>

        </div>

        @include('users.partials.module-permissions', ['permissionState' => []])
        @include('users.partials.device-access', ['user' => new \App\Models\User()])

        <br>

        <button type="submit"
                class="btn btn-success">
            Create User
        </button>

        <a href="{{ route('users.index') }}"
           class="btn btn-secondary">
            Cancel
        </a>

    </form>

</div>

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

