@extends('adminlte::page')

@section('title', 'Profile')

@section('content_header')
<h1>My Profile</h1>
@stop

@section('content')

<div class="row">

    <div class="col-md-6">

        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title">Profile Information</h3>
            </div>
            <div class="card-body">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title">Update Password</h3>
            </div>
            <div class="card-body">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="card card-outline card-danger">
            <div class="card-header">
                <h3 class="card-title">Delete Account</h3>
            </div>
            <div class="card-body">
                @include('profile.partials.delete-user-form')
            </div>
        </div>

    </div>

</div>

@stop
