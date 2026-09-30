@extends('adminlte::page')

@section('title', 'Edit User')

@section('content_header')
<x-settings.header :title="'Edit ' . $user->name" subtitle="Account, role, zone and access" :back="route('users.index')" />
@stop

@section('content')

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST" action="{{ route('users.update', $user->id) }}">
    @csrf
    @method('PUT')

    @include('users.partials.account-fields')

    @if (strtolower(auth()->user()->role) === 'admin')
        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-key mr-1 text-primary"></i> Module &amp; device access</h3></div>
            <div class="card-body">
                @include('users.partials.module-permissions')
                @include('users.partials.device-access')
            </div>
        </div>
    @endif

    <div class="mb-4">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save changes</button>
        <a href="{{ route('users.index') }}" class="btn btn-secondary">Cancel</a>
    </div>
</form>

@stop
