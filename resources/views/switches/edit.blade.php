@extends('adminlte::page')

@section('title', 'Edit Switch')

@section('content_header')
<h1>Edit Switch</h1>
@stop

@section('content')

<div class="card card-outline card-primary col-lg-9 px-0">

    <div class="card-header">
        <h3 class="card-title">Switch &amp; SNMP Details</h3>
    </div>

    <form method="POST" id="switch-form" action="{{ route('switches.update', $switch) }}">
        @csrf
        @method('PUT')

        <div class="card-body">
            @include('switches._form')
        </div>

        <div class="card-footer">
            <button type="submit" class="btn btn-primary" id="switch-submit">Update &amp; Poll</button>
            <a href="{{ route('switches.index') }}" class="btn btn-secondary">Cancel</a>
        </div>

    </form>

</div>

@stop

@section('js')
<script>
document.getElementById('switch-form').addEventListener('submit', function () {
    var btn = document.getElementById('switch-submit');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Polling switch…';
});
</script>
@stop
