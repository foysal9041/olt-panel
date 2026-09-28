@extends('adminlte::page')

@section('title','Edit User')

@section('content')

<div class="card">

<div class="card-header">
    <h3 class="card-title">Edit User</h3>
</div>

<div class="card-body">

    <form method="POST"
          action="{{ route('users.update',$user->id) }}">

        @csrf
        @method('PUT')

        <div class="form-group">
            <label>Name</label>

            <input type="text"
                   name="name"
                   value="{{ $user->name }}"
                   class="form-control"
                   required>
        </div>

        <div class="form-group">
            <label>Email</label>

            <input type="email"
                   name="email"
                   value="{{ $user->email }}"
                   class="form-control"
                   required>
        </div>

        <div class="form-group">
            <label>New Password</label>

            <input type="password"
                   name="password"
                   class="form-control">

            <small>
                Leave blank to keep old password
            </small>
        </div>

        <div class="form-group">
            <label>Role</label>

            <select name="role"
                    class="form-control">

                <option value="admin" {{ $user->role=='admin'?'selected':'' }}>
                    Admin
                </option>

                <option value="noc" {{ $user->role=='noc'?'selected':'' }}>
                    NOC
                </option>

                <option value="operator" {{ $user->role=='operator'?'selected':'' }}>
                    Operator
                </option>

                <option value="viewer" {{ $user->role=='viewer'?'selected':'' }}>
                    Viewer
                </option>

            </select>

        </div>

        <div class="mb-3">
        <label>Username</label>
        <input type="text"
               name="username"
               value="{{ $user->username }}"
               class="form-control"
               required>
        </div>

        <div class="form-group">
            <label>Zone</label>

            <select name="zone" class="form-control select2-zone">

                <option value="all" {{ $user->zone=='all'?'selected':'' }}>
                    All Zones
                </option>

                @foreach($zones as $zone)

                    <option value="{{ $zone }}" {{ $user->zone==$zone?'selected':'' }}>
                        {{ $zone }}
                    </option>

                @endforeach

            </select>

        </div>

        <div class="form-group">
            <label>Status</label>

            <select name="status"
                    class="form-control">

                <option value="1" {{ $user->status ? 'selected':'' }}>
                    Active
                </option>

                <option value="0" {{ !$user->status ? 'selected':'' }}>
                    Disabled
                </option>

            </select>

        </div>

        @if(strtolower(auth()->user()->role) == 'admin')
            @include('users.partials.module-permissions')
        @endif

        <br>

        <button type="submit"
                class="btn btn-success">

            Update User

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
