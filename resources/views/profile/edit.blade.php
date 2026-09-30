@extends('adminlte::page')

@section('title', 'My Profile')

@section('content_header')
<x-settings.header title="My Profile" icon="fas fa-user-circle" subtitle="Your name, email and password" />
@stop

@section('content')

<div class="row">
    <div class="col-lg-6">
        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-id-card mr-1 text-primary"></i> Profile information</h3></div>
            <div class="card-body">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-lock mr-1 text-primary"></i> Change password</h3></div>
            <div class="card-body">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="card acct-panel" style="border: 1px solid #fecdd3">
            <div class="card-header"><h3 class="card-title text-danger"><i class="fas fa-exclamation-triangle mr-1"></i> Delete account</h3></div>
            <div class="card-body">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</div>

@stop
