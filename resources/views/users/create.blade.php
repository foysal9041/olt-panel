@extends('adminlte::page')

@section('title', 'Add User')

@section('content_header')
<x-settings.header title="Add User" subtitle="Create a sign-in and choose what they can open" :back="route('users.index')" />
@stop

@section('content')

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST" action="{{ route('users.store') }}">
    @csrf

    @include('users.partials.account-fields', ['user' => new \App\Models\User()])

    <div class="card acct-panel">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-key mr-1 text-primary"></i> Module &amp; device access</h3></div>
        <div class="card-body">
            @include('users.partials.module-permissions', ['permissionState' => []])
            @include('users.partials.device-access', ['user' => new \App\Models\User()])
        </div>
    </div>

    <div class="mb-4">
        <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Create User</button>
        <a href="{{ route('users.index') }}" class="btn btn-secondary">Cancel</a>
    </div>
</form>

@stop
